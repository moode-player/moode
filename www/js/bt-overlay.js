(function(){
    "use strict";
    var activeCoverUrl = "", stagedCoverUrl = "", refreshBusy = false, observedTarget = null, targetObserver = null, messageObserver = null, documentObserver = null, refreshTimer = null, weatherTimer = null, reconcileFrame = null;
    var currentTrackId = "", luxTimer = null, luxTimerMax = null, trackStartTime = 0;
    
    function installStyles() {
        if (!document.getElementById("custom-bt-style")) {
            var t = document.createElement("style");
            t.id = "custom-bt-style";
            t.textContent = "body.bt-scroll-lock{overflow:hidden !important;touch-action:none !important;}#inpsrc-indicator.custom-bt-active{position:fixed !important;top:0 !important;left:0 !important;width:100vw !important;height:100vh !important;z-index:1030 !important;overflow:hidden !important;background-position:center !important;background-size:cover !important;transition:background-image 1.5s ease-in-out;}#inpsrc-indicator.custom-bt-active #inpsrc-msg,#inpsrc-indicator.custom-bt-active #inpsrc-backdrop,#inpsrc-indicator.custom-bt-active #inpsrc-cover,#inpsrc-indicator.custom-bt-active #inpsrc-metadata-parent,#inpsrc-indicator.custom-bt-active #inpsrc-metadata-refresh{display:none !important;}#custom-bt-ui{position:absolute;inset:0;z-index:1000;box-sizing:border-box;display:flex;flex-direction:column;overflow:hidden;color:#fff;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:linear-gradient(180deg,rgba(0,0,0,0.65),rgba(0,0,0,0.95));backdrop-filter:blur(40px) saturate(150%);-webkit-backdrop-filter:blur(40px) saturate(150%);}#custom-bt-topbar{width:100%;box-sizing:border-box;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;padding:clamp(10px,2vh,18px) clamp(15px,4vw,30px);background:linear-gradient(180deg,rgba(255,255,255,0.08),rgba(255,255,255,0.01));border-bottom:1px solid rgba(255,255,255,0.1);box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:1001;opacity:1;transition:opacity 0.8s ease 0.4s,transform 1s cubic-bezier(0.22,1,0.36,1) 0.4s;}.lux-loading #custom-bt-topbar{opacity:0;transform:translateY(-20px);transition:opacity 0.4s,transform 0.4s;}#custom-bt-top-left{display:flex;align-items:center;justify-self:start;}#custom-bt-status{display:flex;align-items:center;color:rgba(255,255,255,0.85);font-size:clamp(0.9rem,2vw,1.1rem);font-weight:500;letter-spacing:0.5px;}#custom-bt-status svg{width:1.4em;height:1.4em;margin-right:8px;opacity:0.9;}#custom-bt-device{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:35vw;}#custom-bt-clock{justify-self:center;font-size:clamp(0.9rem,2vw,1.1rem);font-weight:500;text-shadow:0 2px 4px rgba(0,0,0,0.5);letter-spacing:0.5px;}#custom-bt-weather{justify-self:end;display:flex;align-items:center;font-size:clamp(0.9rem,2vw,1.1rem);font-weight:500;text-shadow:0 2px 4px rgba(0,0,0,0.5);letter-spacing:0.5px;}#custom-bt-content{flex:1;position:relative;width:100%;height:100%;}#custom-bt-art{position:absolute;left:clamp(20px,5vw,80px);top:50%;width:min(65vh,45vw,500px);height:min(65vh,45vw,500px);margin-top:calc(-0.5 * min(65vh,45vw,500px));border-radius:16px;box-shadow:0 30px 60px rgba(0,0,0,0.7),0 0 0 1px rgba(255,255,255,0.1);object-fit:cover;transform:translateX(0) scale(1);opacity:1;filter:blur(0px);transition:transform 1.2s cubic-bezier(0.22,1,0.36,1),opacity 1s ease,filter 1s ease;will-change:transform,opacity,filter;}#custom-bt-text-normal{position:absolute;left:calc(clamp(20px,5vw,80px)*2 + min(65vh,45vw,500px));top:50%;transform:translateY(-50%) translateX(0);width:calc(100vw - min(65vh,45vw,500px) - clamp(20px,5vw,80px)*3);max-width:1000px;display:flex;flex-direction:column;text-align:left;align-items:flex-start;opacity:1;transition:transform 1.2s cubic-bezier(0.22,1,0.36,1),opacity 1s ease 0.15s;will-change:transform,opacity;}#custom-bt-text-loading{position:absolute;left:50%;top:50%;transform:translate(calc(-50% - 10vw),-50%) scale(0.95);width:90vw;max-width:1200px;display:flex;flex-direction:column;text-align:center;align-items:center;opacity:0;pointer-events:none;transition:transform 1.2s cubic-bezier(0.22,1,0.36,1),opacity 0.7s ease;will-change:transform,opacity;}.bt-title{width:100%;font-size:clamp(1.8rem,5vw,3.6rem);font-weight:800;line-height:1.15;overflow-wrap:anywhere;text-shadow:0 4px 12px rgba(0,0,0,0.6);margin:0;color:#fff;letter-spacing:-0.5px;}.bt-artist{width:100%;color:#e5e7eb;font-size:clamp(1.2rem,3.5vw,2rem);font-weight:600;overflow-wrap:anywhere;text-shadow:0 2px 6px rgba(0,0,0,0.5);margin:6px 0 0 0;}.bt-album{width:100%;color:#9ca3af;font-size:clamp(1rem,2.5vw,1.4rem);font-weight:400;overflow-wrap:anywhere;margin:6px 0 0 0;}.lux-loading #custom-bt-art{transform:translate(calc(50vw - clamp(20px,5vw,80px) - (min(65vh,45vw,500px)/2)),0) scale(0.4);opacity:0;filter:blur(20px);}.lux-loading #custom-bt-text-normal{transform:translateY(-50%) translateX(-5vw) scale(1.05);opacity:0;transition:transform 1.2s cubic-bezier(0.22,1,0.36,1),opacity 0.4s ease;}.lux-loading #custom-bt-text-loading{transform:translate(-50%,-50%) scale(1);opacity:1;}@media (orientation:portrait){#custom-bt-art{left:50%;top:35%;width:min(50vh,80vw,500px);height:min(50vh,80vw,500px);margin-left:calc(-0.5 * min(50vh,80vw,500px));margin-top:calc(-0.5 * min(50vh,80vw,500px));transform:translate(0,0) scale(1);}#custom-bt-text-normal{left:5vw;width:90vw;top:calc(35% + (min(50vh,80vw,500px)/2) + 40px);transform:translateY(0) translateX(0);text-align:center;align-items:center;}.lux-loading #custom-bt-art{transform:translate(0,5vh) scale(0.6);}.lux-loading #custom-bt-text-normal{transform:translateY(5vh) scale(1.05);}.lux-loading #custom-bt-text-loading{transform:translate(-50%,calc(-50% + 5vh)) scale(1);}#custom-bt-text-loading{transform:translate(-50%,-50%) scale(0.95);}}#custom-bt-controls{position:absolute;bottom:clamp(20px,4vh,35px);right:clamp(20px,4vw,35px);display:flex;flex-direction:row;gap:12px;z-index:1001;opacity:1;transition:opacity 0.8s ease 0.5s,transform 1s cubic-bezier(0.22,1,0.36,1) 0.5s;}.lux-loading #custom-bt-controls{opacity:0;transform:translateY(20px);pointer-events:none;transition:opacity 0.4s,transform 0.4s;}#custom-bt-controls button{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);color:rgba(255,255,255,0.65);padding:5px 18px;border-radius:30px;font-size:0.85rem;font-weight:500;letter-spacing:0.5px;cursor:pointer;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);transition:all 0.3s cubic-bezier(0.2,0.8,0.2,1);white-space:nowrap;outline:none;font-family:inherit;}#custom-bt-controls button:hover{background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.95);border-color:rgba(255,255,255,0.35);transform:scale(1.03);}";
            document.head.appendChild(t);
        }
    }
    
    function updateClockAndWeather() {
        var clockEl = document.getElementById("custom-bt-clock");
        if (clockEl) { var now = new Date(); clockEl.textContent = now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }); }
        fetch("https://api.open-meteo.com/v1/forecast?latitude=12.87&longitude=74.84&current_weather=true", { cache: "no-store" }).then(function(res) { if (!res.ok) throw new Error(); return res.json(); }).then(function(data) {
            var weatherEl = document.getElementById("custom-bt-weather");
            if (!weatherEl || !data.current_weather) return;
            var isDay = data.current_weather.is_day === 1;
            var sunSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -4px; margin-right: 6px; opacity: 0.9;"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path></svg>';
            var moonSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -4px; margin-right: 6px; opacity: 0.9;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
            weatherEl.innerHTML = (isDay ? sunSvg : moonSvg) + Math.round(data.current_weather.temperature) + "°C";
        }).catch(function() {});
    }
    
    function startWeatherTimer() { updateClockAndWeather(); if (weatherTimer === null) { weatherTimer = window.setInterval(updateClockAndWeather, 60000); } }
    function stopWeatherTimer() { if (weatherTimer !== null) { window.clearInterval(weatherTimer); weatherTimer = null; } }
    
    function buildUI() {
        var target = document.getElementById("inpsrc-indicator");
        if (!target || document.getElementById("custom-bt-ui")) return;
        var ui = document.createElement("div");
        ui.id = "custom-bt-ui";
        ui.className = "lux-loading";
        ui.innerHTML = '<div id="custom-bt-topbar"><div id="custom-bt-top-left"><div id="custom-bt-status"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6.5 6.5 17.5 17.5 12 23 12 1 17.5 6.5 6.5 17.5"></polyline></svg><span id="custom-bt-device">Connecting...</span></div></div><div id="custom-bt-clock"></div><div id="custom-bt-weather"></div></div><div id="custom-bt-content"><img id="custom-bt-art" src="/images/default-radio-cover.jpg" alt="Album artwork"><div id="custom-bt-text-loading"><div id="title-load" class="bt-title">Connecting...</div><div id="artist-load" class="bt-artist"></div><div id="album-load" class="bt-album"></div></div><div id="custom-bt-text-normal"><div id="title-norm" class="bt-title">Connecting...</div><div id="artist-norm" class="bt-artist"></div><div id="album-norm" class="bt-album"></div></div></div><div id="custom-bt-controls"><button id="custom-bt-btn-blu">Bluetooth Control</button><button id="custom-bt-btn-info">Audio info</button></div>';
        target.appendChild(ui);
        
        var bluBtn = document.getElementById("custom-bt-btn-blu");
        if (bluBtn) {
            bluBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                window.location.href = "blu-config.php";
            });
        }
        
        var infoBtn = document.getElementById("custom-bt-btn-info");
        if (infoBtn) {
            infoBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                if (typeof audioInfoPlayback === "function") {
                    audioInfoPlayback();
                } else if (typeof window.audioInfoPlayback === "function") {
                    window.audioInfoPlayback();
                }
            });
        }
        
        startWeatherTimer();
    }
    
    function commitCover(url) {
        if (!url) return;
        activeCoverUrl = url;
        var artwork = document.getElementById("custom-bt-art"), background = document.getElementById("inpsrc-indicator");
        if (artwork) artwork.src = url;
        if (background) background.style.backgroundImage = 'url("' + url.replace(/"/g, '\\"') + '")';
    }

    function applyCover(coverUrl) {
        if (!coverUrl) {
            evaluateLoadingState();
            return;
        }
        
        var ui = document.getElementById("custom-bt-ui");
        var isLoading = ui && ui.classList.contains("lux-loading");
        
        if (isLoading && stagedCoverUrl === coverUrl) {
            evaluateLoadingState();
            return;
        }
        if (!isLoading && activeCoverUrl === coverUrl) {
            return;
        }
        
        var requestedUrl = coverUrl; 
        var image = new Image();
        image.onload = function() {
            var currentUi = document.getElementById("custom-bt-ui");
            if (currentUi && currentUi.classList.contains("lux-loading")) {
                stagedCoverUrl = requestedUrl; // Stage it silently
            } else {
                commitCover(requestedUrl); // Apply immediately if not animating
            }
            evaluateLoadingState();
        };
        image.onerror = function() { 
            evaluateLoadingState();
        };
        image.src = requestedUrl;
    }

    function evaluateLoadingState(forceTimeout) {
        var ui = document.getElementById("custom-bt-ui");
        if (!ui || !ui.classList.contains("lux-loading")) return;
        
        var elapsed = Date.now() - trackStartTime;
        var hasCustomCover = stagedCoverUrl && stagedCoverUrl.indexOf("default-radio-cover") === -1;
        
        if (forceTimeout === true || elapsed >= 15000 || (elapsed >= 3000 && hasCustomCover)) {
            if (stagedCoverUrl) {
                commitCover(stagedCoverUrl);
                stagedCoverUrl = "";
            } else if (!activeCoverUrl) {
                commitCover("/images/default-radio-cover.jpg");
            }
            
            void ui.offsetWidth; // Force a hardware paint calculation before sliding
            ui.classList.remove("lux-loading");
        }
    }
    
    function fetchMetadata() {
        if (!isBluetoothActive() || refreshBusy) return;
        refreshBusy = true; activateUI();
        
        var fetchUrl = "/images/bt_meta.json?t=" + Date.now();
        fetch(fetchUrl, {cache: "no-store"}).then(function(response) {
            if (!response.ok) throw new Error(); return response.json();
        }).then(function(metadata) {
            if (!isBluetoothActive()) return;
            var ui = document.getElementById("custom-bt-ui");
            
            var trackTitle = metadata.title || "Unknown Track";
            var trackArtist = metadata.artist || "";
            var trackAlbum = (metadata.album && metadata.album !== metadata.title) ? metadata.album : "";
            
            var newTrackSignature = trackTitle + "|" + trackArtist;
            var isNewTrack = (newTrackSignature !== currentTrackId && trackTitle !== "Playback Stopped" && trackTitle !== "No Track Playing");
            
            if (isNewTrack && ui) {
                currentTrackId = newTrackSignature;
                trackStartTime = Date.now();
                stagedCoverUrl = ""; 
                
                ui.classList.add("lux-loading");
                
                if (luxTimer) clearTimeout(luxTimer);
                luxTimer = setTimeout(evaluateLoadingState, 3000); 
                
                if (luxTimerMax) clearTimeout(luxTimerMax);
                luxTimerMax = setTimeout(function(){ evaluateLoadingState(true); }, 15000); 
            }
            
            document.getElementById("title-load").textContent = trackTitle;
            document.getElementById("title-norm").textContent = trackTitle;
            document.getElementById("artist-load").textContent = trackArtist;
            document.getElementById("artist-norm").textContent = trackArtist;
            document.getElementById("album-load").textContent = trackAlbum;
            document.getElementById("album-norm").textContent = trackAlbum;
            
            var deviceEl = document.getElementById("custom-bt-device");
            if (deviceEl) deviceEl.textContent = metadata.device || "Bluetooth Device";
            
            if (metadata.cover_url) {
                applyCover(metadata.cover_url);
            } else {
                evaluateLoadingState();
            }
        }).catch(function() {}).finally(function() { refreshBusy = false; });
    }
    
    function isElementVisible(element) {
        if (!element || !element.isConnected) return false;
        var style = window.getComputedStyle(element);
        return (style.display !== "none" && style.visibility !== "hidden");
    }
    
    function isBluetoothActive() {
        var target = document.getElementById("inpsrc-indicator"), message = document.getElementById("inpsrc-msg");
        if (!target || !message || !isElementVisible(target)) return false;
        var text = String(message.textContent || "").toLowerCase().replace(/\s+/g, " ").trim();
        return (text.indexOf("bluetooth") !== -1 && (text.indexOf("active") !== -1 || text.indexOf("audio") !== -1));
    }
    
    function activateUI() {
        var target = document.getElementById("inpsrc-indicator");
        if (!target) return;
        installStyles(); buildUI();
        if (!target.classList.contains("custom-bt-active")) target.classList.add("custom-bt-active");
        document.body.classList.add("bt-scroll-lock");
    }
    
    function deactivateUI() {
        var target = document.getElementById("inpsrc-indicator"), ui = document.getElementById("custom-bt-ui");
        if (target) { target.classList.remove("custom-bt-active"); target.style.backgroundImage = ""; }
        if (ui) ui.remove();
        document.body.classList.remove("bt-scroll-lock");
        stopWeatherTimer(); activeCoverUrl = ""; stagedCoverUrl = ""; currentTrackId = ""; refreshBusy = false;
        if (luxTimer) { clearTimeout(luxTimer); luxTimer = null; }
        if (luxTimerMax) { clearTimeout(luxTimerMax); luxTimerMax = null; }
    }
    
    function observeNativeElements() {
        var currentTarget = document.getElementById("inpsrc-indicator");
        if (currentTarget === observedTarget) return;
        if (targetObserver) { targetObserver.disconnect(); targetObserver = null; }
        if (messageObserver) { messageObserver.disconnect(); messageObserver = null; }
        observedTarget = currentTarget;
        if (!currentTarget) return;
        
        targetObserver = new MutationObserver(function(mutations) {
            var relevant = false;
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].type === "attributes") { relevant = true; break; }
                if (mutations[i].type === "childList") {
                    for (var j = 0; j < mutations[i].addedNodes.length; j++) {
                        var added = mutations[i].addedNodes[j];
                        if (added.nodeType === 1 && (added.id === "inpsrc-msg" || (added.querySelector && added.querySelector("#inpsrc-msg")))) { relevant = true; break; }
                    }
                }
                if (relevant) break;
            }
            if (relevant) { observeNativeElements(); scheduleReconcile(); }
        });
        targetObserver.observe(currentTarget, { attributes: true, attributeFilter: ["style", "class", "hidden"], childList: true, subtree: false });
        
        var message = document.getElementById("inpsrc-msg");
        if (message) {
            messageObserver = new MutationObserver(scheduleReconcile);
            messageObserver.observe(message, { childList: true, subtree: true, characterData: true });
        }
    }
    
    function nodeContainsIndicator(node) {
        if (!node || node.nodeType !== 1) return false;
        if (node.id === "inpsrc-indicator") return true;
        return Boolean(node.querySelector && node.querySelector("#inpsrc-indicator"));
    }
    
    function watchDocumentForIndicator() {
        if (documentObserver) return;
        documentObserver = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                for (var j = 0; j < mutations[i].addedNodes.length; j++) {
                    if (nodeContainsIndicator(mutations[i].addedNodes[j])) { observeNativeElements(); scheduleReconcile(); return; }
                }
                for (var k = 0; k < mutations[i].removedNodes.length; k++) {
                    if (nodeContainsIndicator(mutations[i].removedNodes[k])) { observeNativeElements(); scheduleReconcile(); return; }
                }
            }
        });
        documentObserver.observe(document.documentElement, { childList: true, subtree: true });
    }
    
    function reconcileState() {
        observeNativeElements();
        if (isBluetoothActive()) { activateUI(); fetchMetadata(); }
        else { deactivateUI(); }
    }
    
    function scheduleReconcile() {
        if (reconcileFrame !== null) return;
        reconcileFrame = window.requestAnimationFrame(function() { reconcileFrame = null; reconcileState(); });
    }
    
    function initialize() {
        installStyles(); watchDocumentForIndicator(); observeNativeElements(); reconcileState();
        if (refreshTimer === null) refreshTimer = window.setInterval(reconcileState, 1200);
    }
    
    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initialize, {once: true}); } else { initialize(); }
})();
