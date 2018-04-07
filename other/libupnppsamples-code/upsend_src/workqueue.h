/* Copyright (C) 2006-2016 J.F.Dockes
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 *   02110-1301 USA
 */
#ifndef _WORKQUEUE_H_INCLUDED_
#define _WORKQUEUE_H_INCLUDED_

#include <thread>
#if HAVE_STD_FUTURE
#include <future>
#endif
#include <string>
#include <queue>
#include <list>
#include <mutex>
#include <condition_variable>

#include "log.h"

/**
 * A WorkQueue manages the synchronisation around a queue of work items,
 * where a number of client threads queue tasks and a number of worker
 * threads take and execute them. The goal is to introduce some level
 * of parallelism between the successive steps of a previously single
 * threaded pipeline. For example data extraction / data preparation / index
 * update, but this could have other uses.
 *
 * There is no individual task status return. In case of fatal error,
 * the client or worker sets an end condition on the queue. A second
 * queue could conceivably be used for returning individual task
 * status.
 *
 * The strange thread functions argument and return values
 * comes from compatibility with an earlier pthread-based
 * implementation.
 */
template <class T> class WorkQueue {
public:

    /** Create a WorkQueue
     * @param name for message printing
     * @param hi number of tasks on queue before clients blocks. Default 0
     *    meaning no limit. hi == -1 means that the queue is disabled.
     * @param lo minimum count of tasks before worker starts. Default 1.
     */
    WorkQueue(const std::string& name, size_t hi = 0, size_t lo = 1)
        : m_name(name), m_high(hi), m_low(lo), m_workers_exited(0),
          m_ok(true), m_clients_waiting(0), m_workers_waiting(0),
          m_tottasks(0), m_nowake(0), m_workersleeps(0), m_clientsleeps(0) {
    }

    ~WorkQueue() {
        if (!m_worker_threads.empty()) {
            setTerminateAndWait();
        }
    }

    /** Start the worker threads.
     *
     * @param nworkers number of threads copies to start.
     * @param start_routine thread function. It should loop
     *      taking (QueueWorker::take()) and executing tasks.
     * @param arg initial parameter to thread function.
     * @return true if ok.
     */
    bool start(int nworkers, void *(workproc)(void *), void *arg) {
        std::unique_lock<std::mutex> lock(m_mutex);
        for (int i = 0; i < nworkers; i++) {
            Worker w;
#if HAVE_STD_FUTURE
            std::packaged_task<void *(void *)> task(workproc);
            w.res = task.get_future();
            w.thr = std::thread(std::move(task), arg);
#else
            w.thr = std::thread(workproc, arg);
#endif
            m_worker_threads.push_back(std::move(w));
        }
        return true;
    }

    /** Add item to work queue, called from client.
     *
     * Sleeps if there are already too many.
     */
    bool put(T t, bool flushprevious = false) {
        std::unique_lock<std::mutex> lock(m_mutex);
        if (!ok()) {
            LOGERR("WorkQueue::put:"  << m_name << ": !ok\n");
            return false;
        }

        while (ok() && m_high > 0 && m_queue.size() >= m_high) {
            m_clientsleeps++;
            // Keep the order: we test ok() AFTER the sleep...
            m_clients_waiting++;
            m_ccond.wait(lock);
            if (!ok()) {
                m_clients_waiting--;
                return false;
            }
            m_clients_waiting--;
        }
        if (flushprevious) {
            while (!m_queue.empty()) {
                m_queue.pop();
            }
        }

        m_queue.push(t);
        if (m_workers_waiting > 0) {
            // Just wake one worker, there is only one new task.
            m_wcond.notify_one();
        } else {
            m_nowake++;
        }

        return true;
    }

    /** Wait until the queue is inactive. Called from client.
     *
     * Waits until the task queue is empty and the workers are all
     * back sleeping. Used by the client to wait for all current work
     * to be completed, when it needs to perform work that couldn't be
     * done in parallel with the worker's tasks, or before shutting
     * down. Work can be resumed after calling this. Note that the
     * only thread which can call it safely is the client just above
     * (which can control the task flow), else there could be
     * tasks in the intermediate queues.
     * To rephrase: there is no warranty on return that the queue is actually
     * idle EXCEPT if the caller knows that no jobs are still being created.
     * It would be possible to transform this into a safe call if some kind
     * of suspend condition was set on the queue by waitIdle(), to be reset by
     * some kind of "resume" call. Not currently the case.
     */
    bool waitIdle() {
        std::unique_lock<std::mutex> lock(m_mutex);
        if (!ok()) {
            LOGERR("WorkQueue::waitIdle:"  << m_name << ": not ok\n");
            return false;
        }

        // We're done when the queue is empty AND all workers are back
        // waiting for a task.
        while (ok() && (m_queue.size() > 0 ||
                        m_workers_waiting != m_worker_threads.size())) {
            m_clients_waiting++;
            m_ccond.wait(lock);
            m_clients_waiting--;
        }

        return ok();
    }

    /** Tell the workers to exit, and wait for them.
     *
     * Does not bother about tasks possibly remaining on the queue, so
     * should be called after waitIdle() for an orderly shutdown.
     */
    void *setTerminateAndWait() {
        std::unique_lock<std::mutex> lock(m_mutex);
        LOGDEB("setTerminateAndWait:"  << m_name << "\n");

        if (m_worker_threads.empty()) {
            // Already called ?
            return (void*)0;
        }

        // Wait for all worker threads to have called workerExit()
        m_ok = false;
        while (m_workers_exited < m_worker_threads.size()) {
            m_wcond.notify_all();
            m_clients_waiting++;
            m_ccond.wait(lock);
            m_clients_waiting--;
        }

        LOGINFO(""  << m_name << ": tasks "  << m_tottasks << " nowakes "  <<
                m_nowake << " wsleeps "  << m_workersleeps << " csleeps "  <<
                m_clientsleeps << "\n");
        // Perform the thread joins and compute overall status
        // Workers return (void*)1 if ok
        void *statusall = (void*)1;
        while (!m_worker_threads.empty()) {
#if HAVE_STD_FUTURE
            void *status = m_worker_threads.front().res.get();
#else
            void *status = (void*) 1;
#endif
            m_worker_threads.front().thr.join();
            if (status == (void *)0) {
                statusall = status;
            }
            m_worker_threads.pop_front();
        }

        // Reset to start state.
        m_workers_exited = m_clients_waiting = m_workers_waiting =
                m_tottasks = m_nowake = m_workersleeps = m_clientsleeps = 0;
        m_ok = true;

        LOGDEB("setTerminateAndWait:"  << m_name << " done\n");
        return statusall;
    }

    /** Take task from queue. Called from worker.
     *
     * Sleeps if there are not enough. Signal if we go to sleep on empty
     * queue: client may be waiting for our going idle.
     */
    bool take(T* tp, size_t *szp = 0) {
        std::unique_lock<std::mutex> lock(m_mutex);
        if (!ok()) {
            LOGDEB("WorkQueue::take:"  << m_name << ": not ok\n");
            return false;
        }

        while (ok() && m_queue.size() < m_low) {
            m_workersleeps++;
            m_workers_waiting++;
            if (m_queue.empty()) {
                m_ccond.notify_all();
            }
            m_wcond.wait(lock);
            if (!ok()) {
                // !ok is a normal condition when shutting down
                m_workers_waiting--;
                return false;
            }
            m_workers_waiting--;
        }

        m_tottasks++;
        *tp = m_queue.front();
        if (szp) {
            *szp = m_queue.size();
        }
        m_queue.pop();
        if (m_clients_waiting > 0) {
            // No reason to wake up more than one client thread
            m_ccond.notify_one();
        } else {
            m_nowake++;
        }
        return true;
    }

    bool waitminsz(size_t sz) {
        std::unique_lock<std::mutex> lock(m_mutex);
        if (!ok()) {
            return false;
        }

        while (ok() && m_queue.size() < sz) {
            m_workersleeps++;
            m_workers_waiting++;
            if (m_queue.empty()) {
                m_ccond.notify_all();
            }
            m_wcond.wait(lock);
            if (!ok()) {
                m_workers_waiting--;
                return false;
            }
            m_workers_waiting--;
        }
        return true;
    }

    /** Advertise exit and abort queue. Called from worker
     *
     * This would happen after an unrecoverable error, or when
     * the queue is terminated by the client. Workers never exit normally,
     * except when the queue is shut down (at which point m_ok is set to
     * false by the shutdown code anyway). The thread must return/exit
     * immediately after calling this.
     */
    void workerExit() {
        LOGDEB("workerExit:"  << m_name << "\n");
        std::unique_lock<std::mutex> lock(m_mutex);
        m_workers_exited++;
        m_ok = false;
        m_ccond.notify_all();
    }

    size_t qsize() {
        std::unique_lock<std::mutex> lock(m_mutex);
        return m_queue.size();
    }

private:
    bool ok() {
        bool isok = m_ok && m_workers_exited == 0 && !m_worker_threads.empty();
        if (!isok) {
            LOGDEB("WorkQueue:ok:" << m_name << ": not ok m_ok " << m_ok <<
                   " m_workers_exited " << m_workers_exited <<
                   " m_worker_threads size " << m_worker_threads.size() <<
                   "\n");
        }
        return isok;
    }

    struct Worker {
        std::thread         thr;
#if HAVE_STD_FUTURE
        std::future<void *> res;
#endif
    };
    
    // Configuration
    std::string m_name;
    size_t m_high;
    size_t m_low;

    // Worker threads having called exit. Used to decide when we're done
    unsigned int m_workers_exited;
    // Status
    bool m_ok;

    // Our threads. 
    std::list<Worker> m_worker_threads;

    // Jobs input queue
    std::queue<T> m_queue;
    
    // Synchronization
    std::condition_variable m_ccond;
    std::condition_variable m_wcond;
    std::mutex m_mutex;

    // Client/Worker threads currently waiting for a job
    unsigned int m_clients_waiting;
    unsigned int m_workers_waiting;

    // Statistics
    unsigned int m_tottasks;
    unsigned int m_nowake;
    unsigned int m_workersleeps;
    unsigned int m_clientsleeps;
};

#endif /* _WORKQUEUE_H_INCLUDED_ */

