#include <linux/signal.h>
#include <linux/slab.h>
#include <linux/module.h>
#include <linux/netdevice.h>
#include <linux/etherdevice.h>
#include <linux/mii.h>
#include <linux/ethtool.h>
#include <linux/usb.h>
#include <linux/crc32.h>
#include <linux/if_vlan.h>
#include <linux/uaccess.h>
#include <linux/list.h>
#include <linux/ip.h>
#include <linux/ipv6.h>
#include <net/ip6_checksum.h>
#include <linux/usb/cdc.h>
#include <linux/suspend.h>
#include <linux/pm_runtime.h>
#include <linux/init.h>
#include <linux/version.h>
#include <linux/in.h>
#include <linux/mdio.h>
#include <uapi/linux/mdio.h>

#include "ax88179_178a.h"

MODULE_AUTHOR(DRIVER_AUTHOR);
MODULE_DESCRIPTION(DRIVER_DESC);
MODULE_LICENSE("GPL");
MODULE_VERSION(DRIVER_VERSION);

/* EEE advertisement is disabled in default setting */
static int bEEE = 0;
module_param(bEEE, int, 0);
MODULE_PARM_DESC(bEEE, "EEE advertisement configuration");

/* Green ethernet advertisement is disabled in default setting */
static int bGETH = 0;
module_param(bGETH, int, 0);
MODULE_PARM_DESC(bGETH, "Green ethernet configuration");

static int
ax_submit_rx(struct ax_device *dev, struct rx_desc *desc, gfp_t mem_flags);

static inline struct net_device_stats *ax_get_stats(struct net_device *dev)
{
	return &dev->stats;
}

static void ax_set_unplug(struct ax_device *axdev)
{
	if (axdev->udev->state == USB_STATE_NOTATTACHED) {
		set_bit(AX88179_UNPLUG, &axdev->flags);
		smp_mb__after_atomic();
	}
}

/*
 * USB Command
 */
static int __ax_usb_read_cmd(struct ax_device *dev, u8 cmd, u8 reqtype,
			     u16 value, u16 index, void *data, u16 size)
{
	void *buf = NULL;
	int err = -ENOMEM;

	if (size) {
		buf = kmalloc(size, GFP_KERNEL);
		if (!buf)
			goto out;
	}

	err = usb_control_msg(dev->udev, usb_rcvctrlpipe(dev->udev, 0),
			      cmd, reqtype, value, index, buf, size,
			      USB_CTRL_GET_TIMEOUT);
	if (err > 0 && err <= size) {
        if (data)
            memcpy(data, buf, err);
        else
            netdev_dbg(dev->netdev,
                "Huh? Data requested but thrown away.\n");
    }
	kfree(buf);
out:
	return err;
}

static int __ax_usb_write_cmd(struct ax_device *dev, u8 cmd, u8 reqtype,
			      u16 value, u16 index, const void *data,
			      u16 size)
{
	void *buf = NULL;
	int err = -ENOMEM;

	if (data) {
		buf = kmemdup(data, size, GFP_KERNEL);
		if (!buf)
			goto out;
	} else {
		if (size) {
		    WARN_ON_ONCE(1);
		    err = -EINVAL;
		    goto out;
		}
   	}

	err = usb_control_msg(dev->udev, usb_sndctrlpipe(dev->udev, 0),
			      cmd, reqtype, value, index, buf, size,
			      USB_CTRL_SET_TIMEOUT);
	kfree(buf);

out:
	return err;
}

int __ax_read_cmd(struct ax_device *dev, u8 cmd, u8 reqtype,
		    u16 value, u16 index, void *data, u16 size)
{
	int ret;

	if (usb_autopm_get_interface(dev->intf) < 0)
		return -ENODEV;
	ret = __ax_usb_read_cmd(dev, cmd, reqtype, value, index,
				data, size);
	usb_autopm_put_interface(dev->intf);
	return ret;
}

int __ax_write_cmd(struct ax_device *dev, u8 cmd, u8 reqtype,
		     u16 value, u16 index, const void *data, u16 size)
{
	int ret;	

	if (usb_autopm_get_interface(dev->intf) < 0)
		return -ENODEV;
	ret = __ax_usb_write_cmd(dev, cmd, reqtype, value, index,
				 data, size);
	usb_autopm_put_interface(dev->intf);
	return ret;
}

int __ax_read_cmd_nopm(struct ax_device *dev, u8 cmd, u8 reqtype,
			  u16 value, u16 index, void *data, u16 size)
{
	return __ax_usb_read_cmd(dev, cmd, reqtype, value, index,
				 data, size);
}

int __ax_write_cmd_nopm(struct ax_device *dev, u8 cmd, u8 reqtype,
			  u16 value, u16 index, const void *data,
			  u16 size)
{
	return __ax_usb_write_cmd(dev, cmd, reqtype, value, index,
				  data, size);
}

static int __ax88179_read_cmd(struct ax_device *dev, u8 cmd, u16 value,
			      u16 index, u16 size, void *data, int in_pm)
{
	int ret;

	int (*fn)(struct ax_device *, u8, u8, u16, u16, void *, u16);

	if (!in_pm)
		fn = __ax_read_cmd;
	else
		fn = __ax_read_cmd_nopm;

	ret = fn(dev, cmd, USB_DIR_IN | USB_TYPE_VENDOR |
		 USB_RECIP_DEVICE, value, index, data, size);

	if (unlikely(ret < 0))
		netdev_warn(dev->netdev,
			    "Failed to read reg cmd 0x%04x, value 0x%04x: %d\n",
			    cmd, value, ret);
	return ret;
}

static int __ax88179_write_cmd(struct ax_device *dev, u8 cmd, u16 value,
			       u16 index, u16 size, void *data, int in_pm)
{
	int ret;

	int (*fn)(struct ax_device *, u8, u8, u16, u16, const void *, u16);

	if (!in_pm)
		fn = __ax_write_cmd;
	else
		fn = __ax_write_cmd_nopm;

	ret = fn(dev, cmd, USB_DIR_OUT | USB_TYPE_VENDOR |
		 USB_RECIP_DEVICE, value, index, data, size);

	if (unlikely(ret < 0))
		netdev_warn(dev->netdev,
			    "Failed to write reg cmd 0x%04x, value 0x%04x: %d\n",
			    cmd, value, ret);

	return ret;
}

static int ax88179_read_cmd_nopm(struct ax_device *dev, u8 cmd, u16 value,
				 u16 index, u16 size, void *data, int eflag)
{
	int ret;

	if (eflag && (2 == size)) {
		u16 buf = 0;
		ret = __ax88179_read_cmd(dev, cmd, value, index, size, &buf, 1);
		le16_to_cpus(&buf);
		*((u16 *)data) = buf;
	} else if (eflag && (4 == size)) {
		u32 buf = 0;
		ret = __ax88179_read_cmd(dev, cmd, value, index, size, &buf, 1);
		le32_to_cpus(&buf);
		*((u32 *)data) = buf;
	} else {
		ret = __ax88179_read_cmd(dev, cmd, value, index, size, data, 1);
	}

	return ret;
}

static int ax88179_write_cmd_nopm(struct ax_device *dev, u8 cmd, u16 value,
				  u16 index, u16 size, void *data)
{
	int ret;

	if (2 == size) {
		u16 buf = 0;
		buf = *((u16 *)data);
		cpu_to_le16s(&buf);
		ret = __ax88179_write_cmd(dev, cmd, value, index,
					  size, &buf, 1);
	} else {
		ret = __ax88179_write_cmd(dev, cmd, value, index,
					  size, data, 1);
	}

	return ret;
}

static int ax88179_read_cmd(struct ax_device *dev, u8 cmd, u16 value, u16 index,
			    u16 size, void *data, int eflag)
{

	int ret;

	if (eflag && (2 == size)) {
		u16 buf = 0;
		ret = __ax88179_read_cmd(dev, cmd, value, index, size, &buf, 0);
		le16_to_cpus(&buf);
		*((u16 *)data) = buf;
	} else if (eflag && (4 == size)) {
		u32 buf = 0;
		ret = __ax88179_read_cmd(dev, cmd, value, index, size, &buf, 0);
		le32_to_cpus(&buf);
		*((u32 *)data) = buf;
	} else {
		ret = __ax88179_read_cmd(dev, cmd, value, index, size, data, 0);
	}

	return ret;
}

static int ax88179_write_cmd(struct ax_device *dev, u8 cmd, u16 value, u16 index,
			     u16 size, void *data)
{
	int ret;

	if (2 == size) {
		u16 buf = 0;
		buf = *((u16 *)data);
		cpu_to_le16s(&buf);
		ret = __ax88179_write_cmd(dev, cmd, value, index,
					  size, &buf, 0);
	} else {
		ret = __ax88179_write_cmd(dev, cmd, value, index,
					  size, data, 0);
	}

	return ret;
}

#if LINUX_VERSION_CODE < KERNEL_VERSION(2, 6, 20)
static void ax88179_async_write_callback(struct urb *urb, struct pt_regs *regs)
#else
static void ax88179_async_write_callback(struct urb *urb)
#endif
{
	struct ax_device_async_handle *asyncdata =
				(struct ax_device_async_handle *)urb->context;

	if (urb->status < 0)
		printk(KERN_ERR "ax88179_async_cmd_callback() failed with %d",
		       urb->status);

	kfree(asyncdata->req);
	kfree(asyncdata);	
	usb_free_urb(urb);
	
}

static void
ax88179_write_cmd_async(struct ax_device *dev, u8 cmd, u16 value, u16 index,
				    u16 size, void *data)
{
	struct usb_ctrlrequest *req = NULL;
	int status = 0;
	struct urb *urb = NULL;
	void *buf = NULL;
	struct ax_device_async_handle *asyncdata = NULL;

	urb = usb_alloc_urb(0, GFP_ATOMIC);
	if (urb == NULL) {
		netdev_err(dev->netdev,
			   "Error allocating URB in write_cmd_async!");
		return;
	}

	req = kmalloc(sizeof(struct usb_ctrlrequest), GFP_ATOMIC);
	if (req == NULL) {
		netdev_err(dev->netdev,
			   "Failed to allocate memory for control request");
		usb_free_urb(urb);
		return;
	}

	asyncdata = (struct ax_device_async_handle*)
			kmalloc(sizeof(struct ax_device_async_handle), GFP_ATOMIC);
	if (asyncdata == NULL) {
		netdev_err(dev->netdev,
			   "Failed to allocate memory for async data");
		kfree(req);
		usb_free_urb(urb);
		return;
	}

	asyncdata->req = req;
	
	if (size == 2) {
		asyncdata->rxctl = *((u16 *)data);
		cpu_to_le16s(&asyncdata->rxctl);
		buf = &asyncdata->rxctl;
	} else {
		memcpy(asyncdata->m_filter, data, size);
		buf = asyncdata->m_filter;
	}

	req->bRequestType = USB_DIR_OUT | USB_TYPE_VENDOR | USB_RECIP_DEVICE;
	req->bRequest = cmd;
	req->wValue = cpu_to_le16(value);
	req->wIndex = cpu_to_le16(index);
	req->wLength = cpu_to_le16(size);

	usb_fill_control_urb(urb, dev->udev,
			     usb_sndctrlpipe(dev->udev, 0),
			     (void *)req, buf, size,
			     ax88179_async_write_callback, asyncdata);

	status = usb_submit_urb(urb, GFP_ATOMIC);
	if (status < 0) {
		netdev_err(dev->netdev,
			   "Error submitting the control message: status=%d",
			   status);
		kfree(req);
		kfree(asyncdata);
		usb_free_urb(urb);
	}
}

/*
 * MDIO Read/Write
 */

static int ax88179_mdio_read(struct net_device *netdev, int phy_id, int reg)
{
	struct ax_device *dev = netdev_priv(netdev);
	u16 res;

	ax88179_read_cmd(dev, AX_ACCESS_PHY, phy_id, (__u16)reg, 2, &res, 1);

	return res;
}

static
void ax88179_mdio_write(struct net_device *netdev, int phy_id, int reg, int val)
{
	struct ax_device *dev = netdev_priv(netdev);
	u16 res = (u16)val;

	ax88179_write_cmd(dev, AX_ACCESS_PHY, phy_id, (__u16)reg, 2, &res);
}

/* End of MDIO Read/Write */

/*
 * URB callback routine
 */

static void ax_read_bulk_callback(struct urb *urb)
{
	struct net_device *netdev;
	int status = urb->status;
	struct rx_desc *desc;
	struct ax_device *axdev;

	desc = urb->context;
	if (!desc)
		return;

	axdev = desc->context;
	if (!axdev)
		return;

	if (test_bit(AX88179_UNPLUG, &axdev->flags))
		return;

	if (!test_bit(AX88179_ENABLE, &axdev->flags))
		return;

	netdev = axdev->netdev;

	if (!netif_carrier_ok(netdev))
		return;

	usb_mark_last_busy(axdev->udev);

	switch (status) {
	case 0:
		if (urb->actual_length < ETH_ZLEN)
			break;

		spin_lock(&axdev->rx_lock);
		list_add_tail(&desc->list, &axdev->rx_done);
		spin_unlock(&axdev->rx_lock);
		napi_schedule(&axdev->napi);
		return;
	case -ESHUTDOWN:
		ax_set_unplug(axdev);
		netif_device_detach(axdev->netdev);
		return;
	case -ENOENT:
		return;	/* the urb is in unlink state */
	case -ETIME:
		if (net_ratelimit())
			netif_warn(axdev, rx_err, netdev,
				   "maybe reset is needed?\n");
		break;
	default:
		if (net_ratelimit())
			netif_warn(axdev, rx_err, netdev,
				   "Rx status %d\n", status);
		break;
	}

	ax_submit_rx(axdev, desc, GFP_ATOMIC);
}

static void ax_write_bulk_callback(struct urb *urb)
{
	struct net_device_stats *stats;
	struct net_device *netdev;
	struct tx_desc *desc;
	struct ax_device *axdev;
	int status = urb->status;

	desc = urb->context;
	if (!desc)
		return;

	axdev = desc->context;
	if (!axdev)
		return;

	netdev = axdev->netdev;
	stats = ax_get_stats(netdev);
	if (status) {
		if (net_ratelimit())
			netif_warn(axdev, tx_err, netdev,
				   "Tx status %d\n", status);
		stats->tx_errors += desc->skb_num;
	} else {
		stats->tx_packets += desc->skb_num;
		stats->tx_bytes += desc->skb_len;
	}

	spin_lock(&axdev->tx_lock);
	list_add_tail(&desc->list, &axdev->tx_free);
	spin_unlock(&axdev->tx_lock);

	usb_autopm_put_interface_async(axdev->intf);

	if (!netif_carrier_ok(netdev))
		return;

	if (!test_bit(AX88179_ENABLE, &axdev->flags))
		return;

	if (test_bit(AX88179_UNPLUG, &axdev->flags))
		return;

	if (!skb_queue_empty(&axdev->tx_queue))
		napi_schedule(&axdev->napi);
}

static void ax_intr_callback(struct urb *urb)
{
	struct ax_device *axdev;
	struct ax_device_int_data *event = NULL;
	int status = urb->status;
	int res;

	axdev = urb->context;
	if (!axdev)
		return;

	if (!test_bit(AX88179_ENABLE, &axdev->flags)) 
		return;

	if (test_bit(AX88179_UNPLUG, &axdev->flags)) 
		return;

	switch (status) {
	case 0:			/* success */
		break;
	case -ECONNRESET:	/* unlink */
	case -ESHUTDOWN:
		netif_device_detach(axdev->netdev);
	case -ENOENT:
	case -EPROTO:
		netif_info(axdev, intr, axdev->netdev,
			   "Stop submitting intr, status %d\n", status);
		return;
	case -EOVERFLOW:
		netif_info(axdev, intr, axdev->netdev,
			   "intr status -EOVERFLOW\n");
		goto resubmit;
	/* -EPIPE:  should clear the halt */
	default:
		netif_info(axdev, intr, axdev->netdev,
			   "intr status %d\n", status);
		goto resubmit;
	}
	
	event = urb->transfer_buffer;
	axdev->link = event->link & AX_INT_PPLS_LINK;

	if (axdev->link) {
		if (!netif_carrier_ok(axdev->netdev)) { //Link up
			set_bit(AX88179_LINK_CHG, &axdev->flags);
			schedule_delayed_work(&axdev->schedule, 0);
		}
	} else {
		if (netif_carrier_ok(axdev->netdev)) { //Link down
			netif_stop_queue(axdev->netdev);
			set_bit(AX88179_LINK_CHG, &axdev->flags);
			schedule_delayed_work(&axdev->schedule, 0);
		}
	}

resubmit:
	res = usb_submit_urb(urb, GFP_ATOMIC);
	if (res == -ENODEV) {
		ax_set_unplug(axdev);
		netif_device_detach(axdev->netdev);
	} else if (res) {
		netif_err(axdev, intr, axdev->netdev,
			  "can't resubmit intr, status %d\n", res);
	}
}

/* End of URB callback routine */

/*
 * Allocate TX/RX memory
 */ 

static inline void *__rx_buf_align(void *data)
{
	return (void *)ALIGN((uintptr_t)data, RX_ALIGN);
}

static inline void *__tx_buf_align(void *data)
{
	return (void *)ALIGN((uintptr_t)data, TX_ALIGN);
}

static void ax_free_buffer(struct ax_device *axdev)
{
	int i;

	for (i = 0; i < AX88179_MAX_RX; i++) {
		usb_free_urb(axdev->rx_list[i].urb);
		axdev->rx_list[i].urb = NULL;

		kfree(axdev->rx_list[i].buffer);
		axdev->rx_list[i].buffer = NULL;
		axdev->rx_list[i].head = NULL;
	}

	for (i = 0; i < AX88179_MAX_TX; i++) {
		usb_free_urb(axdev->tx_list[i].urb);
		axdev->tx_list[i].urb = NULL;

		kfree(axdev->tx_list[i].buffer);
		axdev->tx_list[i].buffer = NULL;
		axdev->tx_list[i].head = NULL;
	}

	usb_free_urb(axdev->intr_urb);
	axdev->intr_urb = NULL;

	kfree(axdev->intr_buff);
	axdev->intr_buff = NULL;
}

static int ax_alloc_buffer(struct ax_device *axdev)
{
	struct net_device *netdev = axdev->netdev;
	struct usb_interface *intf = axdev->intf;
	struct usb_host_interface *alt = intf->cur_altsetting;
	struct usb_host_endpoint *ep_intr = alt->endpoint;
	struct urb *urb;
	int node, i;
	u8 *buf;

	node = netdev->dev.parent ? dev_to_node(netdev->dev.parent) : -1;

	spin_lock_init(&axdev->rx_lock);
	spin_lock_init(&axdev->tx_lock);
	INIT_LIST_HEAD(&axdev->tx_free);
	INIT_LIST_HEAD(&axdev->rx_done);
	skb_queue_head_init(&axdev->tx_queue);
	skb_queue_head_init(&axdev->rx_queue);
	skb_queue_head_init(&axdev->tx_lso_done);

	/* RX */
	for (i = 0; i < AX88179_MAX_RX; i++) {
		buf = kmalloc_node(AX88179_BUF_RX_SIZE, GFP_KERNEL, node);
		if (!buf)
			goto err1;

		if (buf != __rx_buf_align(buf)) {
			kfree(buf);
			buf = kmalloc_node(AX88179_BUF_RX_SIZE + RX_ALIGN,
					   GFP_KERNEL, node);
			if (!buf)
				goto err1;
		}

		urb = usb_alloc_urb(0, GFP_KERNEL);
		if (!urb) {
			kfree(buf);
			goto err1;
		}

		INIT_LIST_HEAD(&axdev->rx_list[i].list);
		axdev->rx_list[i].context = axdev;
		axdev->rx_list[i].urb = urb;
		axdev->rx_list[i].buffer = buf;
		axdev->rx_list[i].head = __rx_buf_align(buf);
	}
	/* TX */
	for (i = 0; i < AX88179_MAX_TX; i++) {
		buf = kmalloc_node(AX88179_BUF_TX_SIZE, GFP_KERNEL, node);
		if (!buf)
			goto err1;

		if (buf != __tx_buf_align(buf)) {
			kfree(buf);
			buf = kmalloc_node(AX88179_BUF_TX_SIZE + TX_ALIGN,
					   GFP_KERNEL, node);
			if (!buf)
				goto err1;
		}

		urb = usb_alloc_urb(0, GFP_KERNEL);
		if (!urb) {
			kfree(buf);
			goto err1;
		}

		INIT_LIST_HEAD(&axdev->tx_list[i].list);
		axdev->tx_list[i].context = axdev;
		axdev->tx_list[i].urb = urb;
		axdev->tx_list[i].buffer = buf;
		axdev->tx_list[i].head = __tx_buf_align(buf);

		list_add_tail(&axdev->tx_list[i].list, &axdev->tx_free);
	}
	/* Interrupt */
	axdev->intr_urb = usb_alloc_urb(0, GFP_KERNEL);
	if (!axdev->intr_urb)
		goto err1;

	axdev->intr_buff = kmalloc(INTBUFSIZE, GFP_KERNEL);
	if (!axdev->intr_buff)
		goto err1;

	axdev->intr_interval = (int)ep_intr->desc.bInterval;
	usb_fill_int_urb(axdev->intr_urb, axdev->udev,
			 usb_rcvintpipe(axdev->udev, 1), axdev->intr_buff,
			 INTBUFSIZE, ax_intr_callback, axdev,
			 axdev->intr_interval);

	return 0;

err1:
	ax_free_buffer(axdev);
	return -ENOMEM;
}
/* End of Allocate TX/RX memory */

/*
 * TX/RX operations
 */

static void ax_lso_complete (struct urb *urb)
{
	struct sk_buff		*skb = (struct sk_buff *) urb->context;
	struct skb_data		*entry = (struct skb_data *) skb->cb;
	struct ax_device 		*dev = entry->dev;

	if (urb->status == 0) {
		dev->netdev->stats.tx_packets++;
		dev->netdev->stats.tx_bytes += entry->length;
	} else {
		dev->netdev->stats.tx_errors++;
	}

	usb_autopm_put_interface_async(dev->intf);
	skb_queue_tail(&dev->tx_lso_done, skb);
}

static struct sk_buff *
ax_tx_fixup(struct ax_device *dev, struct sk_buff *skb, gfp_t flags)
{
	u32 tx_hdr1 = 0, tx_hdr2 = 0;
	int headroom = 0, tailroom = 0;

	tx_hdr1 = skb->len;
	tx_hdr2 = skb_shinfo(skb)->gso_size;

	if ((dev->netdev->features & NETIF_F_SG) && skb_linearize(skb))
		return NULL;

	headroom = skb_headroom(skb);
	tailroom = skb_tailroom(skb);

	if ((headroom + tailroom) >= 8) {
		if (headroom < 8) {
			skb->data = memmove(skb->head + 8, skb->data, skb->len);
			skb_set_tail_pointer(skb, skb->len);
		}
	} else {
		struct sk_buff *skb2 = NULL;
		skb2 = skb_copy_expand(skb, 8, 0, flags);
		dev_kfree_skb_any(skb);
		skb = skb2;
		if (!skb)
			return NULL;
	}

	skb_push(skb, 4);
	cpu_to_le32s(&tx_hdr2);
	skb_copy_to_linear_data(skb, &tx_hdr2, 4);

	skb_push(skb, 4);
	cpu_to_le32s(&tx_hdr1);
	skb_copy_to_linear_data(skb, &tx_hdr1, 4);

	return skb;

}

static int ax_lso_xmit (struct sk_buff *skb, struct net_device *net)
{
	struct ax_device *axdev = netdev_priv(net);
	struct net_device_stats *stats;
	struct urb *urb = NULL;
	struct skb_data	*entry;
	int ret, length;	
	
	skb = ax_tx_fixup(axdev, skb, GFP_ATOMIC);	
	if (!skb)
		goto drop;

	length = skb->len;

	if (!(urb = usb_alloc_urb (0, GFP_ATOMIC))) {
		netif_dbg(axdev, tx_err, axdev->netdev, "no urb\n");
		goto drop;
	}

	entry = (struct skb_data *) skb->cb;
	entry->urb = urb;
	entry->dev = axdev;
	entry->length = length;

	ret = usb_autopm_get_interface_async(axdev->intf);
	if (ret < 0)
		goto drop;

	usb_fill_bulk_urb (urb, axdev->udev, usb_sndbulkpipe(axdev->udev, 3),
			skb->data, skb->len, ax_lso_complete, skb);	

	ret = usb_submit_urb(urb, GFP_ATOMIC);
	if (ret < 0)
		usb_autopm_put_interface_async(axdev->intf);

	if (ret) {		
drop:
		stats = ax_get_stats(axdev->netdev);
		stats->tx_dropped++;
		if (skb)
			dev_kfree_skb_any (skb);
		if (urb)
			usb_free_urb (urb);
	} else
		netif_dbg(axdev, tx_queued, axdev->netdev,
			  "> tx, len %d, type 0x%x\n", length, skb->protocol);

	return ret;
}

static struct tx_desc *ax_get_tx_desc(struct ax_device *dev)
{
	struct tx_desc *desc = NULL;
	unsigned long flags;

	if (list_empty(&dev->tx_free))
		return NULL;

	spin_lock_irqsave(&dev->tx_lock, flags);
	if (!list_empty(&dev->tx_free)) {
		struct list_head *cursor;

		cursor = dev->tx_free.next;
		list_del_init(cursor);
		desc = list_entry(cursor, struct tx_desc, list);
	}
	spin_unlock_irqrestore(&dev->tx_lock, flags);

	return desc;
}

static int ax_tx_desc_fill(struct ax_device *axdev, struct tx_desc *desc)
{
	struct sk_buff_head skb_head, *tx_queue = &axdev->tx_queue;
	struct net_device_stats *stats = &axdev->netdev->stats;
	struct sk_buff *lso_skb = NULL;
	int remain, ret;
	u8 *tx_data;

	__skb_queue_head_init(&skb_head);
	spin_lock(&tx_queue->lock);
	skb_queue_splice_init(tx_queue, &skb_head);
	spin_unlock(&tx_queue->lock);

	tx_data = desc->head;
	desc->skb_num = 0;
	desc->skb_len = 0;
	remain = AX88179_BUF_TX_SIZE;

	while (remain >= ETH_ZLEN + 8) {
		struct sk_buff *skb;
		u32 *tx_hdr;

		skb = __skb_dequeue(&skb_head);
		if (!skb)
			break;

		if (skb_shinfo(skb)->gso_size > 0) {
			lso_skb = skb;
			break;
		}

		if ((skb->len + AX_TX_HEADER_LEN) > remain) {
			__skb_queue_head(&skb_head, skb);
			break;
		}		
		
		memset(tx_data, 0, AX_TX_HEADER_LEN);
		tx_hdr = (u32 *)tx_data;
		*tx_hdr = skb->len;
		cpu_to_le32s(*tx_hdr);		
		tx_data += 8;

		if (skb_copy_bits(skb, 0, tx_data, skb->len) < 0) {
			stats->tx_dropped++;
			dev_kfree_skb_any(skb);
			continue;
		}
	
		tx_data += skb->len;
		desc->skb_len += skb->len;
		desc->skb_num++;		

		dev_kfree_skb_any(skb);

		tx_data = __tx_buf_align(tx_data);
		remain = AX88179_BUF_TX_SIZE - (int)((void *)tx_data - desc->head);
	}

	if (!skb_queue_empty(&skb_head)) {
		spin_lock(&tx_queue->lock);
		skb_queue_splice(&skb_head, tx_queue);
		spin_unlock(&tx_queue->lock);
	}

	netif_tx_lock(axdev->netdev);

	if (netif_queue_stopped(axdev->netdev) &&
	    skb_queue_len(&axdev->tx_queue) < axdev->tx_qlen)
		netif_wake_queue(axdev->netdev);

	netif_tx_unlock(axdev->netdev);

	ret = usb_autopm_get_interface_async(axdev->intf);
	if (ret < 0)
		goto out_tx_fill;

	usb_fill_bulk_urb(desc->urb, axdev->udev, usb_sndbulkpipe(axdev->udev, 3),
			  desc->head, (int)(tx_data - (u8 *)desc->head),
			  (usb_complete_t)ax_write_bulk_callback, desc);

	ret = usb_submit_urb(desc->urb, GFP_ATOMIC);
	if (ret < 0) {
		usb_autopm_put_interface_async(axdev->intf);
		if (lso_skb != NULL) {
			stats->tx_dropped++;
			dev_kfree_skb_any (lso_skb);
		}
	} else {
		if (lso_skb != NULL)
			ax_lso_xmit(lso_skb, axdev->netdev);
	}

out_tx_fill:
	return ret;
}

static void ax_tx_bottom(struct ax_device *axdev)
{
	int res;

	do {
		struct tx_desc *desc;

		if (skb_queue_empty(&axdev->tx_queue))
			break;

		desc = ax_get_tx_desc(axdev);
		if (!desc)
			break;

		res = ax_tx_desc_fill(axdev, desc);
		if (res) {
			struct net_device *netdev = axdev->netdev;

			if (res == -ENODEV) {
				ax_set_unplug(axdev);
				netif_device_detach(netdev);
			} else {
				struct net_device_stats *stats;
				unsigned long flags;

				stats = ax_get_stats(netdev);
				stats->tx_dropped += desc->skb_num;

				spin_lock_irqsave(&axdev->tx_lock, flags);
				list_add_tail(&desc->list, &axdev->tx_free);
				spin_unlock_irqrestore(&axdev->tx_lock, flags);
			}
		}
	} while (res == 0);
}

static void ax_bottom_half(struct ax_device *axdev)
{
	if (test_bit(AX88179_UNPLUG, &axdev->flags))
		return;

	if (!test_bit(AX88179_ENABLE, &axdev->flags))
		return;

	if (!netif_carrier_ok(axdev->netdev))
		return;

	clear_bit(AX_SCHEDULE_NAPI, &axdev->flags);	

	ax_tx_bottom(axdev);
}

static void ax_rx_checksum(struct sk_buff *skb, u32 *pkt_hdr)
{
	skb->ip_summed = CHECKSUM_NONE;

	/* checksum error bit is set */
	if ((*pkt_hdr & AX_RXHDR_L3CSUM_ERR) ||
	    (*pkt_hdr & AX_RXHDR_L4CSUM_ERR))
		return;

	/* It must be a TCP or UDP packet with a valid checksum */
	if (((*pkt_hdr & AX_RXHDR_L4_TYPE_MASK) == AX_RXHDR_L4_TYPE_TCP) ||
	    ((*pkt_hdr & AX_RXHDR_L4_TYPE_MASK) == AX_RXHDR_L4_TYPE_UDP))
		skb->ip_summed = CHECKSUM_UNNECESSARY;
}

static int ax_rx_bottom(struct ax_device *axdev, int budget)
{
	unsigned long flags;
	struct list_head *cursor, *next, rx_queue;
	int ret = 0, work_done = 0;
	struct napi_struct *napi = &axdev->napi;
	struct net_device *netdev = axdev->netdev;
	struct net_device_stats *stats = ax_get_stats(netdev);

	if (!skb_queue_empty(&axdev->rx_queue)) {
		while (work_done < budget) {
			struct sk_buff *skb = __skb_dequeue(&axdev->rx_queue);						
			unsigned int pkt_len;
			if (!skb)
				break;

			pkt_len = skb->len;	
			napi_gro_receive(napi, skb);
			work_done++;
			stats->rx_packets++;
			stats->rx_bytes += pkt_len;
		}
	}

	if (list_empty(&axdev->rx_done))
		goto out1;

	INIT_LIST_HEAD(&rx_queue);
	spin_lock_irqsave(&axdev->rx_lock, flags);
	list_splice_init(&axdev->rx_done, &rx_queue);
	spin_unlock_irqrestore(&axdev->rx_lock, flags);

	list_for_each_safe(cursor, next, &rx_queue) {
		struct rx_desc *desc;
		struct urb *urb;
		u8 *rx_data;
		u32 rx_hdr = 0, pkt_hdr = 0, pkt_hdr_curr = 0, hdr_off = 0;
		u32 aa = 0;
		int pkt_cnt = 0;

		list_del_init(cursor);

		desc = list_entry(cursor, struct rx_desc, list);
		urb = desc->urb;
		if (urb->actual_length < ETH_ZLEN)
			goto submit;

		/* RX Desc */
		memcpy(&rx_hdr, (desc->head + urb->actual_length - 4),
		       sizeof(rx_hdr));
		le32_to_cpus(&rx_hdr);		

		pkt_cnt = rx_hdr & 0xFF;
		pkt_hdr_curr = hdr_off = rx_hdr >> 16;
				
		/* Check Bulk IN data */
		aa = (urb->actual_length - (((pkt_cnt + 2) & 0xFE) * 4));		
		if ((aa != hdr_off) ||
		    (hdr_off >= urb->actual_length) ||
		    (pkt_cnt == 0))
			continue;
		
		rx_data = desc->head;
		while (pkt_cnt--) {
			u32 pkt_len;
			struct sk_buff *skb;

			memcpy(&pkt_hdr, (desc->head + pkt_hdr_curr),
			       sizeof(pkt_hdr));
			pkt_hdr_curr += 4;

			/* limite the skb numbers for rx_queue */
			if (unlikely(skb_queue_len(&axdev->rx_queue) >= 1000)) {
				break;
			}

			le32_to_cpus(&pkt_hdr);
			pkt_len = (pkt_hdr >> 16) & 0x1FFF;
			pkt_len -= NET_IP_ALIGN;

			/* Check CRC or runt packet */
			if ((pkt_hdr & AX_RXHDR_CRC_ERR) ||
			    (pkt_hdr & AX_RXHDR_DROP_ERR)) {
				goto find_next_rx;
			}			

			skb = napi_alloc_skb(napi, pkt_len);
			if (!skb) {
				stats->rx_dropped++;
				goto find_next_rx;
			}
			
			skb_put(skb, pkt_len);		
			memcpy(skb->data, (rx_data + NET_IP_ALIGN), pkt_len);

			if (NET_IP_ALIGN == 0)
				skb_pull(skb, 2);

			ax_rx_checksum(skb, &pkt_hdr);
		
			skb->protocol = eth_type_trans(skb, netdev);

			if (work_done < budget) {
				napi_gro_receive(napi, skb);
				work_done++;
				stats->rx_packets++;
				stats->rx_bytes += pkt_len;
			} else {
				__skb_queue_tail(&axdev->rx_queue, skb);
			}
find_next_rx:
			rx_data += (pkt_len + 7) & 0xFFF8;
		}

submit:
		if (!ret) {
			ret = ax_submit_rx(axdev, desc, GFP_ATOMIC);
		} else {
			urb->actual_length = 0;
			list_add_tail(&desc->list, next);
		}
	}

	if (!list_empty(&rx_queue)) {
		spin_lock_irqsave(&axdev->rx_lock, flags);
		list_splice_tail(&rx_queue, &axdev->rx_done);
		spin_unlock_irqrestore(&axdev->rx_lock, flags);
	}
out1:
	return work_done;
}

static
int ax_submit_rx(struct ax_device *dev, struct rx_desc *desc, gfp_t mem_flags)
{
	int ret;		

	/* The rx would be stopped, so skip submitting */
	if (test_bit(AX88179_UNPLUG, &dev->flags) ||
	    !test_bit(AX88179_ENABLE, &dev->flags) ||
	    !netif_carrier_ok(dev->netdev))
		return 0;

	usb_fill_bulk_urb(desc->urb, dev->udev, usb_rcvbulkpipe(dev->udev, 2),
			  desc->head, AX88179_BUF_RX_SIZE,
			  (usb_complete_t)ax_read_bulk_callback, desc);

	ret = usb_submit_urb(desc->urb, mem_flags);
	if (ret == -ENODEV) {
		ax_set_unplug(dev);
		netif_device_detach(dev->netdev);
	} else if (ret) {
		struct urb *urb = desc->urb;
		unsigned long flags;

		urb->actual_length = 0;
		spin_lock_irqsave(&dev->rx_lock, flags);
		list_add_tail(&desc->list, &dev->rx_done);
		spin_unlock_irqrestore(&dev->rx_lock, flags);

		netif_err(dev, rx_err, dev->netdev,
			  "Couldn't submit rx[%p], ret = %d\n", desc, ret);

		napi_schedule(&dev->napi);
	}

	return ret;
}

/* End of TX/RX operations */

/*
 * NAPI Polling routine
 */

static inline int __ax_poll(struct ax_device *axdev, int budget)
{
	struct napi_struct *napi = &axdev->napi;
	int work_done;
	struct sk_buff		*skb;
	struct skb_data		*entry;

	work_done = ax_rx_bottom(axdev, budget);
	ax_bottom_half(axdev);

	/* Free LSO skb */
	while ((skb = skb_dequeue (&axdev->tx_lso_done)) != NULL) {			
		entry = (struct skb_data *) skb->cb;
		usb_free_urb (entry->urb);
		dev_kfree_skb (skb);		
	}

	if (work_done < budget) {
#if LINUX_VERSION_CODE < KERNEL_VERSION(4,10,0)
		napi_complete_done(napi, work_done);
#else
		if (!napi_complete_done(napi, work_done))
			goto out;
#endif
		if (!list_empty(&axdev->rx_done))
			napi_schedule(napi);
		else if (!skb_queue_empty(&axdev->tx_queue) &&
			 !list_empty(&axdev->tx_free))
			napi_schedule(napi);
	}

#if LINUX_VERSION_CODE >= KERNEL_VERSION(4,10,0)
out:
#endif
	return work_done;
}

static int ax_poll(struct napi_struct *napi, int budget)
{
	struct ax_device *axdev = container_of(napi, struct ax_device, napi);

	return __ax_poll(axdev, budget);
}

/* End of NAPI Polling routine */

static void ax_drop_queued_tx(struct ax_device *dev)
{
	struct net_device_stats *stats = ax_get_stats(dev->netdev);
	struct sk_buff_head skb_head, *tx_queue = &dev->tx_queue;
	struct sk_buff *skb;

	if (skb_queue_empty(tx_queue))
		return;

	__skb_queue_head_init(&skb_head);
	spin_lock_bh(&tx_queue->lock);
	skb_queue_splice_init(tx_queue, &skb_head);
	spin_unlock_bh(&tx_queue->lock);

	while ((skb = __skb_dequeue(&skb_head))) {
		dev_kfree_skb(skb);
		stats->tx_dropped++;
	}
}

static void ax88179_tx_timeout(struct net_device *netdev)
{
	struct ax_device *dev = netdev_priv(netdev);

	netif_warn(dev, tx_err, netdev, "Tx timeout\n");

	usb_queue_reset_device(dev->intf);
}

static netdev_tx_t ax88179_start_xmit(struct sk_buff *skb,
				      struct net_device *netdev)
{
	struct ax_device *axdev = netdev_priv(netdev);

	skb_tx_timestamp(skb);

	skb_queue_tail(&axdev->tx_queue, skb);

	if (!list_empty(&axdev->tx_free)) {
		if (test_bit(AX_SELECTIVE_SUSPEND, &axdev->flags)) {
			set_bit(AX_SCHEDULE_NAPI, &axdev->flags);
			schedule_delayed_work(&axdev->schedule, 0);
		} else {
			usb_mark_last_busy(axdev->udev);
			napi_schedule(&axdev->napi);
		}
	} else if (skb_queue_len(&axdev->tx_queue) > axdev->tx_qlen) {
		netif_stop_queue(netdev);
	}

	return NETDEV_TX_OK;
}

static void ax_set_tx_qlen(struct ax_device *dev)
{
	struct net_device *netdev = dev->netdev;

	dev->tx_qlen = AX88179_BUF_TX_SIZE / (netdev->mtu + ETH_FCS_LEN + 8);
}

static int ax_start_rx(struct ax_device *axdev)
{
	int i, ret = 0;

	INIT_LIST_HEAD(&axdev->rx_done);
	for (i = 0; i < AX88179_MAX_RX; i++) {
		INIT_LIST_HEAD(&axdev->rx_list[i].list);
		ret = ax_submit_rx(axdev, &axdev->rx_list[i], GFP_KERNEL);
		if (ret)
			break;
	}

	if (ret && ++i < AX88179_MAX_RX) {
		struct list_head rx_queue;
		unsigned long flags;

		INIT_LIST_HEAD(&rx_queue);

		do {
			struct rx_desc *desc = &axdev->rx_list[i++];
			struct urb *urb = desc->urb;

			urb->actual_length = 0;
			list_add_tail(&desc->list, &rx_queue);
		} while (i < AX88179_MAX_RX);

		spin_lock_irqsave(&axdev->rx_lock, flags);
		list_splice_tail(&rx_queue, &axdev->rx_done);
		spin_unlock_irqrestore(&axdev->rx_lock, flags);
	}

	return ret;
}

static int ax_stop_rx(struct ax_device *axdev)
{
	int i;

	for (i = 0; i < AX88179_MAX_RX; i++)
		usb_kill_urb(axdev->rx_list[i].urb);

	while (!skb_queue_empty(&axdev->rx_queue))
		dev_kfree_skb(__skb_dequeue(&axdev->rx_queue));

	return 0;
}

static void ax_disable(struct ax_device *axdev)
{
	int i;

	if (test_bit(AX88179_UNPLUG, &axdev->flags)) {
		ax_drop_queued_tx(axdev);
		return;
	}	

	ax_drop_queued_tx(axdev);

	for (i = 0; i < AX88179_MAX_TX; i++)
		usb_kill_urb(axdev->tx_list[i].urb);

	ax_stop_rx(axdev);	
}


#if LINUX_VERSION_CODE >= KERNEL_VERSION(2, 6, 39)
static int
#if LINUX_VERSION_CODE >= KERNEL_VERSION(3, 3, 0)
ax88179_set_features(struct net_device *net, netdev_features_t features)
#else
ax88179_set_features(struct net_device *net, u32 features)
#endif

{
	u8 *tmp8;
	struct ax_device *dev = netdev_priv(net);	

#if LINUX_VERSION_CODE >= KERNEL_VERSION(3, 3, 0)
	netdev_features_t changed = net->features ^ features;
#else
	u32 changed = net->features ^ features;
#endif

	tmp8 = kmalloc(1, GFP_KERNEL);
	if (!tmp8)
		return -ENOMEM;

	if (changed & NETIF_F_IP_CSUM) {
		ax88179_read_cmd(dev, AX_ACCESS_MAC, AX_TXCOE_CTL,
				 1, 1, tmp8, 0);
		*tmp8 ^= AX_TXCOE_TCP | AX_TXCOE_UDP;
		ax88179_write_cmd(dev, AX_ACCESS_MAC, AX_TXCOE_CTL, 1, 1, tmp8);
	}

	if (changed & NETIF_F_IPV6_CSUM) {
		ax88179_read_cmd(dev, AX_ACCESS_MAC, AX_TXCOE_CTL,
				 1, 1, tmp8, 0);
		*tmp8 ^= AX_TXCOE_TCPV6 | AX_TXCOE_UDPV6;
		ax88179_write_cmd(dev, AX_ACCESS_MAC, AX_TXCOE_CTL, 1, 1, tmp8);
	}

	if (changed & NETIF_F_RXCSUM) {
		ax88179_read_cmd(dev, AX_ACCESS_MAC, AX_RXCOE_CTL,
				 1, 1, tmp8, 0);
		*tmp8 ^= AX_RXCOE_IP | AX_RXCOE_TCP | AX_RXCOE_UDP |
		       AX_RXCOE_TCPV6 | AX_RXCOE_UDPV6;
		ax88179_write_cmd(dev, AX_ACCESS_MAC, AX_RXCOE_CTL, 1, 1, tmp8);
	}

	kfree(tmp8);

	return 0;
}
#endif

static int ax_link_reset(struct ax_device *dev)
{	
	u8 reg8[5], link_sts;
	u16 mode, reg16, delay = 10 * HZ;
	u32 reg32;
	unsigned long jtimeout = 0;

	mode = AX_MEDIUM_TXFLOW_CTRLEN | AX_MEDIUM_RXFLOW_CTRLEN;
	
	ax88179_read_cmd_nopm(dev, AX_ACCESS_MAC, PHYSICAL_LINK_STATUS,
			 1, 1, &link_sts, 0);

	jtimeout = jiffies + delay;
	while(time_before(jiffies, jtimeout)) {
		ax88179_read_cmd_nopm(dev, AX_ACCESS_PHY, AX88179_PHY_ID, GMII_PHY_PHYSR, 2, &reg16, 1);
			if (reg16 & GMII_PHY_PHYSR_LINK) {
			break;
		}
	}

	if (!(reg16 & GMII_PHY_PHYSR_LINK))
		return 0;
	else if (GMII_PHY_PHYSR_GIGA == (reg16 & GMII_PHY_PHYSR_SMASK)) {
		mode |= AX_MEDIUM_GIGAMODE;
		if (dev->netdev->mtu > 1500)
			mode |= AX_MEDIUM_JUMBO_EN;

		if (link_sts & AX_USB_SS)
			memcpy(reg8, &AX88179_BULKIN_SIZE[0], 5);
		else if (link_sts & AX_USB_HS)
			memcpy(reg8, &AX88179_BULKIN_SIZE[1], 5);
		else
			memcpy(reg8, &AX88179_BULKIN_SIZE[3], 5);
	} else if (GMII_PHY_PHYSR_100 == (reg16 & GMII_PHY_PHYSR_SMASK)) {
		mode |= AX_MEDIUM_PS;	/* Bit 9 : PS */
		if (link_sts & (AX_USB_SS | AX_USB_HS))
			memcpy(reg8, &AX88179_BULKIN_SIZE[2], 5);
		else
			memcpy(reg8, &AX88179_BULKIN_SIZE[3], 5);
	} else
		memcpy(reg8, &AX88179_BULKIN_SIZE[3], 5);

	/* RX bulk configuration */
	ax88179_write_cmd_nopm(dev, AX_ACCESS_MAC, AX_RX_BULKIN_QCTRL, 5, 5, reg8);

	if (reg16 & GMII_PHY_PHYSR_FULL)
		mode |= AX_MEDIUM_FULL_DUPLEX;	/* Bit 1 : FD */	
	
	ax88179_read_cmd_nopm(dev, 0x81, 0x8c, 0, 4, &reg32, 1);
	delay = HZ / 2;
	if (reg32 & 0x40000000) {
		u16 temp16 = 0;
		ax88179_write_cmd_nopm(dev, AX_ACCESS_MAC, AX_RX_CTL, 2, 2, &temp16);

		/* Configure default medium type => giga */
		ax88179_write_cmd_nopm(dev, AX_ACCESS_MAC, AX_MEDIUM_STATUS_MODE,
				  2, 2, &mode);

		jtimeout = jiffies + delay;

		while (time_before(jiffies, jtimeout)) {
			
			ax88179_read_cmd_nopm(dev, 0x81, 0x8c, 0, 4, &reg32, 1);
		
			if (!(reg32 & 0x40000000))
				break;

			reg32 = 0x80000000;
			ax88179_write_cmd(dev, 0x81, 0x8c, 0, 4, &reg32);
		}
		
		temp16 = AX_RX_CTL_DROPCRCERR | AX_RX_CTL_START | AX_RX_CTL_AP |
		 	 AX_RX_CTL_AMALL | AX_RX_CTL_AB | AX_RX_CTL_IPE; 
		ax88179_write_cmd_nopm(dev, AX_ACCESS_MAC, AX_RX_CTL,
				  2, 2, &temp16);
	}

	reg16 = AX_RX_CTL_DROPCRCERR | AX_RX_CTL_START | AX_RX_CTL_AP |
		 	 AX_RX_CTL_AMALL | AX_RX_CTL_AB | AX_RX_CTL_IPE; 
	ax88179_write_cmd_nopm(dev, AX_ACCESS_MAC, AX_RX_CTL,
				  2, 2, &reg16);

	mode |= AX_MEDIUM_RECEIVE_EN;

	/* Configure default medium type => giga */
	ax88179_write_cmd_nopm(dev, AX_ACCESS_MAC, AX_MEDIUM_STATUS_MODE,
			  2, 2, &mode);	

	if  (!test_bit(AX_SELECTIVE_SUSPEND, &dev->flags)) {
		mii_check_media(&dev->mii, 1, 1);
	}

	return 0;
}

static void ax_set_carrier(struct ax_device *axdev)
{
	struct net_device *netdev = axdev->netdev;
	struct napi_struct *napi = &axdev->napi;

	if (axdev->link) {	
		if (!netif_carrier_ok(netdev)) {
				ax_link_reset(axdev);				
				netif_stop_queue(netdev);
				napi_disable(napi);
				netif_carrier_on(netdev);
				ax_start_rx(axdev);
				napi_enable(napi);
				netif_wake_queue(netdev);
		} else if (netif_queue_stopped(netdev) &&
			   skb_queue_len(&axdev->tx_queue) < axdev->tx_qlen) {
				netif_wake_queue(netdev);
		}
	} else {
		if (netif_carrier_ok(netdev)) {
				netif_carrier_off(netdev);
				napi_disable(napi);
				ax_disable(axdev);
				napi_enable(napi);
				netif_info(axdev, link, netdev, "link down\n");
		}
	}
}

static inline void __ax_work_func(struct ax_device *dev)
{
	if (test_bit(AX88179_UNPLUG, &dev->flags) || !netif_running(dev->netdev))
		return;

	if (usb_autopm_get_interface(dev->intf) < 0)
		return;

	if (!test_bit(AX88179_ENABLE, &dev->flags))
		goto out1;

	if (!mutex_trylock(&dev->control)) {
		schedule_delayed_work(&dev->schedule, 0);
		goto out1;
	}

	if (test_and_clear_bit(AX88179_LINK_CHG, &dev->flags))
		ax_set_carrier(dev);

	if (test_and_clear_bit(AX_SCHEDULE_NAPI, &dev->flags) &&
	    netif_carrier_ok(dev->netdev))
		napi_schedule(&dev->napi);

	mutex_unlock(&dev->control);

out1:
	usb_autopm_put_interface(dev->intf);
}

static void ax_work_func_t(struct work_struct *work)
{
	struct ax_device *dev = container_of(work, struct ax_device, schedule.work);

	__ax_work_func(dev);
}

/*
 * IOCTL operations
 */

static int ax88179_ioctl(struct net_device *net, struct ifreq *rq, int cmd)
{
	struct ax_device *axdev = netdev_priv(net);

	return  generic_mii_ioctl(&axdev->mii, if_mii(rq), cmd, NULL);
}
/* End of IOCTL operations */

/*
 * Ethtool operations
 */

static void ax88179_get_drvinfo(struct net_device *net,
				struct ethtool_drvinfo *info)
{
	struct ax_device *axdev = netdev_priv(net);

	strlcpy (info->driver, MODULENAME, sizeof(info->driver));
	strlcpy (info->version, DRIVER_VERSION, sizeof info->version);
	usb_make_path (axdev->udev, info->bus_info, sizeof info->bus_info);

	info->eedump_len = 0x3e;
}
#if LINUX_VERSION_CODE < KERNEL_VERSION(4, 20, 0)
static int ax88179_get_settings(struct net_device *net, struct ethtool_cmd *cmd)
{
	struct ax_device *axdev = netdev_priv(net);
	return mii_ethtool_gset(&axdev->mii, cmd);
}

static int ax88179_set_settings(struct net_device *net, struct ethtool_cmd *cmd)
{
	struct ax_device *axdev = netdev_priv(net);
	return mii_ethtool_sset(&axdev->mii, cmd);
}
#endif
static u32 ax88179_get_msglevel(struct net_device *netdev)
{
	struct ax_device *axdev = netdev_priv(netdev);

	return axdev->msg_enable;
}

static void ax88179_set_msglevel(struct net_device *netdev, u32 value)
{
	struct ax_device *axdev = netdev_priv(netdev);

	axdev->msg_enable = value;
}

static void
ax88179_get_wol(struct net_device *net, struct ethtool_wolinfo *wolinfo)
{
	struct ax_device *axdev = netdev_priv(net);
	u8 reg8;	

	if (ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_MONITOR_MODE,
			     1, 1, &reg8, 0) < 0) {
		wolinfo->supported = 0;
		wolinfo->wolopts = 0;
		return;
	}

	wolinfo->supported = WAKE_PHY | WAKE_MAGIC;

	if (reg8 & AX_MONITOR_MODE_RWLC)
		wolinfo->wolopts |= WAKE_PHY;
	if (reg8 & AX_MONITOR_MODE_RWMP)
		wolinfo->wolopts |= WAKE_MAGIC;
}

static int
ax88179_set_wol(struct net_device *net, struct ethtool_wolinfo *wolinfo)
{
	struct ax_device *axdev = netdev_priv(net);
	u8 reg8 = 0;

	if (wolinfo->wolopts & WAKE_PHY)
		reg8 |= AX_MONITOR_MODE_RWLC;
	else
		reg8 &= ~AX_MONITOR_MODE_RWLC;

	if (wolinfo->wolopts & WAKE_MAGIC)
		reg8 |= AX_MONITOR_MODE_RWMP;
	else
		reg8 &= ~AX_MONITOR_MODE_RWMP;

	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_MONITOR_MODE, 1, 1, &reg8);	

	return 0;
}

static
int ax88179_get_link_ksettings(struct net_device *netdev,
			       struct ethtool_link_ksettings *cmd)
{
	struct ax_device *axdev = netdev_priv(netdev);
	int ret;

	if (!axdev->mii.mdio_read)
		return -EOPNOTSUPP;

	ret = usb_autopm_get_interface(axdev->intf);
	if (ret < 0)
		goto out;

	mutex_lock(&axdev->control);

	mii_ethtool_get_link_ksettings(&axdev->mii, cmd);

	mutex_unlock(&axdev->control);

	usb_autopm_put_interface(axdev->intf);

out:
	return ret;
}

static int ax88179_set_link_ksettings(struct net_device *netdev,
				      const struct ethtool_link_ksettings *cmd)
{
	struct ax_device *axdev = netdev_priv(netdev);
	int ret;

	ret = usb_autopm_get_interface(axdev->intf);
	if (ret < 0)
		goto out;

	mutex_lock(&axdev->control);

	mii_ethtool_set_link_ksettings(&axdev->mii, cmd);

	mutex_unlock(&axdev->control);

	usb_autopm_put_interface(axdev->intf);

out:
	return ret;
}

static const struct ethtool_ops ax88179_ethtool_ops = {
	.get_drvinfo  	= ax88179_get_drvinfo,
#if LINUX_VERSION_CODE < KERNEL_VERSION(4, 20, 0)
	.get_settings 	= ax88179_get_settings,
	.set_settings 	= ax88179_set_settings,
#endif	
	.get_link     	= ethtool_op_get_link,
	.get_msglevel 	= ax88179_get_msglevel,
	.set_msglevel 	= ax88179_set_msglevel,
	.get_wol 	= ax88179_get_wol,
	.set_wol 	= ax88179_set_wol,
	.get_link_ksettings = ax88179_get_link_ksettings,
	.set_link_ksettings = ax88179_set_link_ksettings,
};
/* End of Ethtool operations */

/*
 * Network operations
 */

static int ax88179_set_mac_addr(struct net_device *net, void *p)
{
	struct ax_device *dev = netdev_priv(net);
	struct sockaddr *addr = p;
	int ret;

	if (netif_running(net))
		return -EBUSY;
	if (!is_valid_ether_addr(addr->sa_data))
		return -EADDRNOTAVAIL;

	memcpy(net->dev_addr, addr->sa_data, ETH_ALEN);	

	/* Set the MAC address */
	ret = ax88179_write_cmd(dev, AX_ACCESS_MAC, AX_NODE_ID, ETH_ALEN,
				 ETH_ALEN, net->dev_addr);
	if (ret < 0)
		return ret;

	return 0;

}

static int ax_check_eeprom(struct ax_device *axdev)
{
	u8 i = 0;
	u8 buf[2];
	u8 eeprom[20];
	u16 csum = 0, delay = HZ / 10;
	unsigned long jtimeout = 0;	

	/* Read EEPROM content */
	for (i = 0 ; i < 6; i++) {
		buf[0] = i;
		if (ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_SROM_ADDR,
				      1, 1, buf) < 0) 
			return -EINVAL;

		buf[0] = EEP_RD;
		if (ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_SROM_CMD,
				      1, 1, buf) < 0) 
			return -EINVAL;

		jtimeout = jiffies + delay;
		do {
			ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_SROM_CMD,
					 1, 1, buf, 0);

			if (time_after(jiffies, jtimeout)) 
				return -EINVAL;
		} while (buf[0] & EEP_BUSY);

		ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_SROM_DATA_LOW,
				 2, 2, &eeprom[i * 2], 0);

		if ((i == 0) && (eeprom[0] == 0xFF)) 
			return -EINVAL;
	}

	csum = eeprom[6] + eeprom[7] + eeprom[8] + eeprom[9];
	csum = (csum >> 8) + (csum & 0xff);

	if ((csum + eeprom[10]) == 0xff) 
		return AX_EEP_EFUSE_CORRECT;
	else 
		return -EINVAL;

	return AX_EEP_EFUSE_CORRECT;
}

static int ax_check_efuse(struct ax_device *axdev, void *ledmode)
{
	u8	i = 0;	
	u16	csum = 0;
	u8	efuse[64];

	if (ax88179_read_cmd(axdev, AX_ACCESS_EFUSE, 0, 64, 64, efuse, 0) < 0)
		return -EINVAL;

	if (efuse[0] == 0xFF) 
		return -EINVAL;

	for (i = 0; i < 64; i++)
		csum = csum + efuse[i];

	while (csum > 255)
		csum = (csum & 0x00FF) + ((csum >> 8) & 0x00FF);

	if (csum == 0xFF) {
		memcpy((u8 *)ledmode, &efuse[51], 2);
		return AX_EEP_EFUSE_CORRECT;
	} else 
		return -EINVAL;

	return AX_EEP_EFUSE_CORRECT;
}

static int ax_convert_old_led(struct ax_device *axdev, u8 efuse, void *ledvalue)
{
	u8 ledmode = 0;
	u16 reg16;
	u16 led = 0;	

	/* loaded the old eFuse LED Mode */
	if (efuse) {
		if (ax88179_read_cmd(axdev, AX_ACCESS_EFUSE, 0x18,
				     1, 2, &reg16, 1) < 0)
			return -EINVAL;
		ledmode = (u8)(reg16 & 0xFF);
	} else { /* loaded the old EEprom LED Mode */
		if (ax88179_read_cmd(axdev, AX_ACCESS_EEPROM, 0x3C,
				     1, 2, &reg16, 1) < 0)
			return -EINVAL;
		ledmode = (u8) (reg16 >> 8);
	}
	netdev_dbg(axdev->netdev, "Old LED Mode = %02X\n", ledmode);

	switch (ledmode) {
	case 0xFF:
		led = LED0_ACTIVE | LED1_LINK_10 | LED1_LINK_100 |
		      LED1_LINK_1000 | LED2_ACTIVE | LED2_LINK_10 |
		      LED2_LINK_100 | LED2_LINK_1000 | LED_VALID;
		break;
	case 0xFE:
		led = LED0_ACTIVE | LED1_LINK_1000 | LED2_LINK_100 | LED_VALID;
		break;
	case 0xFD:
		led = LED0_ACTIVE | LED1_LINK_1000 | LED2_LINK_100 |
		      LED2_LINK_10 | LED_VALID;
		break;
	case 0xFC:
		led = LED0_ACTIVE | LED1_ACTIVE | LED1_LINK_1000 | LED2_ACTIVE |
		      LED2_LINK_100 | LED2_LINK_10 | LED_VALID;
		break;
	default:
		led = LED0_ACTIVE | LED1_LINK_10 | LED1_LINK_100 |
		      LED1_LINK_1000 | LED2_ACTIVE | LED2_LINK_10 |
		      LED2_LINK_100 | LED2_LINK_1000 | LED_VALID;
		break;
	}

	memcpy((u8 *)ledvalue, &led, 2);

	return 0;
}

static void ax_Gether_setting(struct ax_device *axdev)
{
	u16 reg16;

	if (bGETH) {
		reg16 = 0x03;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  31, 2, &reg16);
		reg16 = 0x3247;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  25, 2, &reg16);
		reg16 = 0x05;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  31, 2, &reg16);
		reg16 = 0x0680;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  1, 2, &reg16);
		reg16 = 0;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  31, 2, &reg16);
	} else {
		reg16 = 0x03;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  31, 2, &reg16);
		reg16 = 0x3246;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  25, 2, &reg16);
		reg16 = 0;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  31, 2, &reg16);
	}
}

static int ax_LED_setting(struct ax_device *axdev)
{	
	u16 ledvalue = 0, delay = HZ / 10;
	u16 ledact, ledlink;
	u16 reg16;	
	u8 value;
	unsigned long jtimeout = 0;
	
	/* Check AX88179 version. UA1 or UA2 */
	ax88179_read_cmd(axdev, AX_ACCESS_MAC, GENERAL_STATUS, 1, 1, &value, 0);

	/* UA1 */
	if (!(value & AX_SECLD)) {
		value = AX_GPIO_CTRL_GPIO3EN | AX_GPIO_CTRL_GPIO2EN |
			AX_GPIO_CTRL_GPIO1EN;
		if (ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_GPIO_CTRL,
				      1, 1, &value) < 0)
			return -EINVAL;
	}

	/* check EEprom */
	if (ax_check_eeprom(axdev) == AX_EEP_EFUSE_CORRECT) {
		value = 0x42;
		if (ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_SROM_ADDR,
				      1, 1, &value) < 0)
			return -EINVAL;

		value = EEP_RD;
		if (ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_SROM_CMD,
				      1, 1, &value) < 0)
			return -EINVAL;

		jtimeout = jiffies + delay;
		do {
			ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_SROM_CMD,
					 1, 1, &value, 0);

			ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_SROM_CMD,
					 1, 1, &value, 0);

			if (time_after(jiffies, jtimeout))
				return -EINVAL;
		} while (value & EEP_BUSY);

		ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_SROM_DATA_HIGH,
				 1, 1, &value, 0);
		ledvalue = (value << 8);
		ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_SROM_DATA_LOW,
				 1, 1, &value, 0);
		ledvalue |= value;

		/* load internal ROM for defaule setting */
		if ((ledvalue == 0xFFFF) || ((ledvalue & LED_VALID) == 0))
			ax_convert_old_led(axdev, 0, &ledvalue);

	} else if (ax_check_efuse(axdev, &ledvalue) ==
				       AX_EEP_EFUSE_CORRECT) { /* check efuse */
		if ((ledvalue == 0xFFFF) || ((ledvalue & LED_VALID) == 0))
			ax_convert_old_led(axdev, 0, &ledvalue);
	} else {
		ax_convert_old_led(axdev, 0, &ledvalue);
	}

	reg16 = GMII_PHY_PAGE_SELECT_EXT;
	ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
			  GMII_PHY_PAGE_SELECT, 2, &reg16);

	reg16 = 0x2c;
	ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
			  GMII_PHYPAGE, 2, &reg16);

	ax88179_read_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
			 GMII_LED_ACTIVE, 2, &ledact, 1);

	ax88179_read_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
			 GMII_LED_LINK, 2, &ledlink, 1);

	ledact &= GMII_LED_ACTIVE_MASK;
	ledlink &= GMII_LED_LINK_MASK;

	if (ledvalue & LED0_ACTIVE)
		ledact |= GMII_LED0_ACTIVE;
	if (ledvalue & LED1_ACTIVE)
		ledact |= GMII_LED1_ACTIVE;
	if (ledvalue & LED2_ACTIVE)
		ledact |= GMII_LED2_ACTIVE;

	if (ledvalue & LED0_LINK_10)
		ledlink |= GMII_LED0_LINK_10;
	if (ledvalue & LED1_LINK_10)
		ledlink |= GMII_LED1_LINK_10;
	if (ledvalue & LED2_LINK_10)
		ledlink |= GMII_LED2_LINK_10;

	if (ledvalue & LED0_LINK_100)
		ledlink |= GMII_LED0_LINK_100;
	if (ledvalue & LED1_LINK_100)
		ledlink |= GMII_LED1_LINK_100;
	if (ledvalue & LED2_LINK_100)
		ledlink |= GMII_LED2_LINK_100;

	if (ledvalue & LED0_LINK_1000)
		ledlink |= GMII_LED0_LINK_1000;
	if (ledvalue & LED1_LINK_1000)
		ledlink |= GMII_LED1_LINK_1000;
	if (ledvalue & LED2_LINK_1000)
		ledlink |= GMII_LED2_LINK_1000;
	
	ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
			  GMII_LED_ACTIVE, 2, &ledact);

	ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
			  GMII_LED_LINK, 2, &ledlink);

	reg16 = GMII_PHY_PAGE_SELECT_PAGE0;
	ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
			  GMII_PHY_PAGE_SELECT, 2, &reg16);

	/* LED full duplex setting */
	reg16 = 0;
	if (ledvalue & LED0_FD)
		reg16 |= 0x01;
	else if ((ledvalue & LED0_USB3_MASK) == 0)
		reg16 |= 0x02;

	if (ledvalue & LED1_FD)
		reg16 |= 0x04;
	else if ((ledvalue & LED1_USB3_MASK) == 0)
		reg16 |= 0x08;

	if (ledvalue & LED2_FD) /* LED2_FD */
		reg16 |= 0x10;
	else if ((ledvalue & LED2_USB3_MASK) == 0) /* LED2_USB3 */
		reg16 |= 0x20;

	ax88179_write_cmd(axdev, AX_ACCESS_MAC, 0x73, 1, 1, &reg16);

	return 0;
}

static void ax_EEE_setting(struct ax_device *axdev)
{
	u16 reg16;
	
	if (bEEE) { /* Enable */
		reg16 = 0x07;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  GMII_PHY_MACR, 2, &reg16);
		reg16 = 0x3c;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  GMII_PHY_MAADR, 2, &reg16);
		reg16 = 0x4007;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  GMII_PHY_MACR, 2, &reg16);
		reg16 = 0x06;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  GMII_PHY_MAADR, 2, &reg16);
	} else {
		reg16 = 0x07;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  GMII_PHY_MACR, 2, &reg16);
		reg16 = 0x3c;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  GMII_PHY_MAADR, 2, &reg16);
		reg16 = 0x4007;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  GMII_PHY_MACR, 2, &reg16);
		reg16 = 0x00;
		ax88179_write_cmd(axdev, AX_ACCESS_PHY, AX88179_PHY_ID,
				  GMII_PHY_MAADR, 2, &reg16);
	}
}

static int ax_hw_init(struct ax_device *axdev)
{
	u32 reg32;
	u16 reg16;
	u8 reg8;
	u8 buf[6] = {0};	
	
	reg32 = 0;
	ax88179_write_cmd(axdev, 0x81, 0x310, 0, 4, &reg32);

	/* Power up ethernet PHY */
	reg16 = 0;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_PHYPWR_RSTCTL, 2, 2, &reg16);
	reg16 = AX_PHYPWR_RSTCTL_IPRL;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_PHYPWR_RSTCTL, 2, 2, &reg16);
	msleep(200);

	reg8 = AX_CLK_SELECT_ACS | AX_CLK_SELECT_BCS;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_CLK_SELECT, 1, 1, &reg8);
	msleep(100);
	
	/* RX bulk configuration, default for USB3.0 to Giga*/
	memcpy(buf, &AX88179_BULKIN_SIZE[0], 5);
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_RX_BULKIN_QCTRL, 5, 5, buf);	

	reg8 = 0x34;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_PAUSE_WATERLVL_LOW,
			  1, 1, &reg8);

	reg8 = 0x52;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_PAUSE_WATERLVL_HIGH,
			  1, 1, &reg8);

	/* Disable auto-power-OFF GigaPHY after ethx down*/
	ax88179_write_cmd(axdev, 0x91, 0, 0, 0, NULL);

	/* Enable checksum offload */
	reg8 = AX_RXCOE_IP | AX_RXCOE_TCP | AX_RXCOE_UDP |
	       AX_RXCOE_TCPV6 | AX_RXCOE_UDPV6;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_RXCOE_CTL, 1, 1, &reg8);

	reg8 = AX_TXCOE_IP | AX_TXCOE_TCP | AX_TXCOE_UDP |
	       AX_TXCOE_TCPV6 | AX_TXCOE_UDPV6;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_TXCOE_CTL, 1, 1, &reg8);

	reg8 = AX_MONITOR_MODE_PMETYPE | AX_MONITOR_MODE_PMEPOL |
	       AX_MONITOR_MODE_RWLC | AX_MONITOR_MODE_RWMP;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_MONITOR_MODE, 1, 1, &reg8);	

	ax_LED_setting(axdev);

	ax_EEE_setting(axdev);

	ax_Gether_setting(axdev);

	/* Restart autoneg */
	mii_nway_restart(&axdev->mii);

	netif_carrier_off(axdev->netdev);

	return 0;

}

static int ax88179_open(struct net_device *netdev)
{
	struct ax_device *dev = netdev_priv(netdev);
	int res = 0;



	res = ax_alloc_buffer(dev);
	if (res) 
		goto out;	

	res = usb_autopm_get_interface(dev->intf);
	if (res < 0) 
		goto out_free;	

	mutex_lock(&dev->control);

	res = ax_hw_init(dev);
	if (res < 0)
		goto out_free;

	netif_carrier_off(netdev);	
	
	smp_mb__before_atomic();
	set_bit(AX88179_ENABLE, &dev->flags);
	smp_mb__after_atomic();	

	ax_set_tx_qlen(dev);

	res = usb_submit_urb(dev->intr_urb, GFP_KERNEL);
	if (res) {
		if (res == -ENODEV)
			netif_device_detach(dev->netdev);
		netif_warn(dev, ifup, netdev, "intr_urb submit failed: %d\n",
			   res);
		goto out_unlock;
	}

	napi_enable(&dev->napi);
	netif_start_queue(netdev);

	mutex_unlock(&dev->control);

	usb_autopm_put_interface(dev->intf);

	return 0;

out_unlock:
	mutex_unlock(&dev->control);
	usb_autopm_put_interface(dev->intf);
out_free:
	ax_free_buffer(dev);
out:
	return res;
}

static int ax88179_close(struct net_device *netdev)
{
	struct ax_device *axdev = netdev_priv(netdev);
	u16 reg16;
	u8 reg8;
	int res = 0;

	netif_carrier_off(netdev);	

	/* Configure RX control register => stop operation */
	reg16 = AX_RX_CTL_STOP;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_MEDIUM_STATUS_MODE,
			  2, 2, &reg16);

	reg8 = 0;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_CLK_SELECT, 1, 1, &reg8);

	/* Power down ethernet PHY */
	reg16 = AX_PHYPWR_RSTCTL_BZ;
	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_PHYPWR_RSTCTL,
			  2, 2, &reg16);
	msleep(200);

	napi_disable(&axdev->napi);
	smp_mb__before_atomic();
	clear_bit(AX88179_ENABLE, &axdev->flags);
	smp_mb__after_atomic();
	usb_kill_urb(axdev->intr_urb);
	cancel_delayed_work_sync(&axdev->schedule);
	netif_stop_queue(axdev->netdev);	

	res = usb_autopm_get_interface(axdev->intf);
	if (res < 0 || test_bit(AX88179_UNPLUG, &axdev->flags)) {
		ax_drop_queued_tx(axdev);
		ax_stop_rx(axdev);
	} 

	ax_disable(axdev);

	ax_free_buffer(axdev);

	return res;
}

static int ax88179_change_mtu(struct net_device *net, int new_mtu)
{
	struct ax_device *axdev = netdev_priv(net);
	u16 reg16;

	if (new_mtu <= 0 || new_mtu > 4088)
		return -EINVAL;

	net->mtu = new_mtu;

	if (net->mtu > 1500) {
		ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_MEDIUM_STATUS_MODE,
				 2, 2, &reg16, 1);
		reg16 |= AX_MEDIUM_JUMBO_EN;
		ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_MEDIUM_STATUS_MODE,
				  2, 2, &reg16);
	} else {
		ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_MEDIUM_STATUS_MODE,
				 2, 2, &reg16, 1);
		reg16 &= ~AX_MEDIUM_JUMBO_EN;
		ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_MEDIUM_STATUS_MODE,
				  2, 2, &reg16);
	}

	return 0;
}

static void ax88179_set_multicast(struct net_device *net)
{
	struct ax_device *axdev = netdev_priv(net);
	u8 *m_filter = axdev->m_filter;
	int mc_count = 0;

	if (!test_bit(AX88179_ENABLE, &axdev->flags)) {
		return;
	}

#if LINUX_VERSION_CODE < KERNEL_VERSION(2, 6, 35)
	mc_count = net->mc_count;
#else
	mc_count = netdev_mc_count(net);
#endif

	axdev->rxctl = (AX_RX_CTL_START | AX_RX_CTL_AB | AX_RX_CTL_IPE);

	if (net->flags & IFF_PROMISC) {
		axdev->rxctl |= AX_RX_CTL_PRO;
	} else if (net->flags & IFF_ALLMULTI
		   || mc_count > AX_MAX_MCAST) {
		axdev->rxctl |= AX_RX_CTL_AMALL;
	} else if (mc_count == 0) {
		/* just broadcast and directed */
	} else {
		/* We use the 20 byte dev->data
		 * for our 8 byte filter buffer
		 * to avoid allocating memory that
		 * is tricky to free later */
		u32 crc_bits = 0;

#if LINUX_VERSION_CODE < KERNEL_VERSION(2, 6, 35)
		struct dev_mc_list *mc_list = net->mc_list;
		int i = 0;

		memset(m_filter, 0, AX_MCAST_FILTER_SIZE);

		/* Build the multicast hash filter. */
		for (i = 0; i < net->mc_count; i++) {
			crc_bits =
			    ether_crc(ETH_ALEN,
				      mc_list->dmi_addr) >> 26;
			*(m_filter + (crc_bits >> 3)) |=
				1 << (crc_bits & 7);
			mc_list = mc_list->next;
		}
#else
		struct netdev_hw_addr *ha = NULL;
		memset(m_filter, 0, AX_MCAST_FILTER_SIZE);
		netdev_for_each_mc_addr(ha, net) {
			crc_bits = ether_crc(ETH_ALEN, ha->addr) >> 26;
			*(m_filter + (crc_bits >> 3)) |=
				1 << (crc_bits & 7);
		}
#endif
		ax88179_write_cmd_async(axdev, AX_ACCESS_MAC,
					AX_MULTI_FILTER_ARRY,
					AX_MCAST_FILTER_SIZE,
					AX_MCAST_FILTER_SIZE, m_filter);

		axdev->rxctl |= AX_RX_CTL_AM;
	}

	ax88179_write_cmd_async(axdev, AX_ACCESS_MAC, AX_RX_CTL,
				2, 2, &axdev->rxctl);
}

static const struct net_device_ops ax88179_netdev_ops = {
	.ndo_open		= ax88179_open,
	.ndo_stop		= ax88179_close,
	.ndo_do_ioctl		= ax88179_ioctl,
	.ndo_start_xmit		= ax88179_start_xmit,
	.ndo_tx_timeout		= ax88179_tx_timeout,
	.ndo_set_features	= ax88179_set_features,
	.ndo_set_rx_mode	= ax88179_set_multicast,
	.ndo_set_mac_address	= ax88179_set_mac_addr,
	.ndo_change_mtu		= ax88179_change_mtu,
	.ndo_validate_addr	= eth_validate_addr,
};
/* End of Network operations */

/*
 * Driver Operartion
 */

static int ax_access_eeprom_mac(struct ax_device *axdev, u8 *buf, u8 offset, int wflag)
{
	int ret = 0, i;
	u16* tmp = (u16*)buf;
	
	for (i = 0; i < (ETH_ALEN >> 1); i++) {
		if (wflag) {
			u16 tmp16;			
			
			tmp16 = cpu_to_le16(*(tmp + i));
			ret = ax88179_write_cmd(axdev, AX_ACCESS_EEPROM,
						offset + i, 1, 2, &tmp16);
			if (ret < 0)
				break;

			mdelay(15);
		}
		else {
			ret = ax88179_read_cmd(axdev, AX_ACCESS_EEPROM,
						offset + i, 1, 2, tmp + i, 0);
			if (ret < 0)
				break;
		}
	}

	if (!wflag) {
		if (ret < 0) {
			netdev_dbg(axdev->netdev,
				   "Failed to read MAC address from EEPROM: %d\n",
				   ret);
			return ret;
		}
		memcpy(axdev->netdev->dev_addr, buf, ETH_ALEN);
	}
	else {
		/* reload eeprom data */
		ret = ax88179_write_cmd(axdev, AX_RELOAD_EEPROM_EFUSE, 0, 0, 0, 0);
		if (ret < 0) 
			return ret;		
	}

	return 0;
}

static int ax_check_ether_addr(struct ax_device *axdev)
{
	unsigned char *tmp = (unsigned char*)axdev->netdev->dev_addr;
	u8 default_mac[6] = {0, 0x0e, 0xc6, 0x81, 0x79, 0x01};
	u8 default_mac_178a[6] = {0, 0x0e, 0xc6, 0x81, 0x78, 0x01};

	if (((*((u8*)tmp) == 0) &&
	    (*((u8*)tmp + 1) == 0) &&
	    (*((u8*)tmp + 2) == 0)) ||
	    !is_valid_ether_addr((u8*)tmp) ||
	    !memcmp(axdev->netdev->dev_addr, default_mac, ETH_ALEN) ||
	    !memcmp(axdev->netdev->dev_addr, default_mac_178a, ETH_ALEN)) {

		printk("Found invalid EEPROM MAC address value\n");

		eth_random_addr(tmp);

		*tmp = 0;
		*(tmp + 1) = 0x0E;
		*(tmp + 2) = 0xC6;
		*(tmp + 3) = 0x8E;

		return -EADDRNOTAVAIL;	
	} 
	return 0;
}

static int ax_get_mac(struct ax_device *axdev, u8* buf)
{
	int ret, i;

	ret = ax_access_eeprom_mac(axdev, buf, 0x0, 0);
	if (ret < 0)
		goto out;

	if (ax_check_ether_addr(axdev)) {
		ret = ax_access_eeprom_mac(axdev, axdev->netdev->dev_addr, 0x0, 1);
		if (ret < 0) {
			netdev_err(axdev->netdev,
				   "Failed to write MAC to EEPROM: %d", ret);
			goto out;
		}

		msleep(5);

		ret = ax88179_read_cmd(axdev, AX_ACCESS_MAC, AX_NODE_ID,
				       ETH_ALEN, ETH_ALEN, buf, 0);
		if (ret < 0) {
			netdev_err(axdev->netdev,
				   "Failed to read MAC address: %d", ret);
			goto out;
		}

		for (i = 0; i < ETH_ALEN; i++)
			if (*(axdev->netdev->dev_addr + i) != *((u8*)buf + i)) {
				netdev_warn(axdev->netdev,
					    "Found invalid EEPROM part or \
					    non-EEPROM");
				break;
			}
	}

	memcpy(axdev->netdev->perm_addr, axdev->netdev->dev_addr, ETH_ALEN);

	ax88179_write_cmd(axdev, AX_ACCESS_MAC, AX_NODE_ID, ETH_ALEN,
			  ETH_ALEN, axdev->netdev->dev_addr);
	
	if (ret < 0) {
		netdev_err(axdev->netdev, "Failed to write MAC address: %d", ret);
		goto out;
	}

	return 0;
out:
	return ret;
}

static int ax88179_probe(struct usb_interface *intf,
			 const struct usb_device_id *id)
{
	struct usb_device *udev = interface_to_usbdev(intf);
	//struct usb_driver *driver = to_usb_driver(intf->dev.driver);	
	struct net_device *netdev;
	struct ax_device *axdev;
	u8 mac_addr[6] = {0};
	int ret;

	netdev = alloc_etherdev(sizeof(struct ax_device));
	if (!netdev) {
		dev_err(&intf->dev, "Out of memory\n");
		return -ENOMEM;
	}
	
	axdev = netdev_priv(netdev);

	netdev->watchdog_timeo = AX88179_TX_TIMEOUT;
	netdev->netdev_ops = &ax88179_netdev_ops;
	netdev->ethtool_ops = &ax88179_ethtool_ops;

	axdev->udev = udev;
	axdev->netdev = netdev;
	axdev->intf = intf;

	mutex_init(&axdev->control);
	INIT_DELAYED_WORK(&axdev->schedule, ax_work_func_t);	

	netdev->features |= NETIF_F_IP_CSUM | NETIF_F_IPV6_CSUM;
	netdev->hw_features |= NETIF_F_IP_CSUM | NETIF_F_IPV6_CSUM |
			       NETIF_F_SG | NETIF_F_TSO;	

	axdev->mii.supports_gmii = 1;
	axdev->mii.dev = netdev;
	axdev->mii.mdio_read = ax88179_mdio_read;
	axdev->mii.mdio_write = ax88179_mdio_write;
	axdev->mii.phy_id_mask = 0xff;
	axdev->mii.reg_num_mask = 0xff;
	axdev->mii.phy_id = AX88179_PHY_ID;
	axdev->mii.force_media = 0;
	axdev->mii.advertising = ADVERTISE_10HALF | ADVERTISE_10FULL |
			         ADVERTISE_100HALF | ADVERTISE_100FULL;
	axdev->advertising = ADVERTISED_10baseT_Half | ADVERTISED_10baseT_Full |
			   ADVERTISED_100baseT_Half | ADVERTISED_100baseT_Full |
			   ADVERTISED_1000baseT_Full;
	intf->needs_remote_wakeup = 1;

	memset(mac_addr, 0, ETH_ALEN);
	ret = ax_get_mac(axdev, mac_addr);
	if (ret)
		goto out;
	netdev_dbg(axdev->netdev, "MAC [%02x-%02x-%02x-%02x-%02x-%02x]\n",
		   axdev->netdev->dev_addr[0], axdev->netdev->dev_addr[1],
		   axdev->netdev->dev_addr[2], axdev->netdev->dev_addr[3],
		   axdev->netdev->dev_addr[4], axdev->netdev->dev_addr[5]);

	usb_set_intfdata(intf, axdev);
	netif_napi_add(netdev, &axdev->napi, ax_poll, AX88179_NAPI_WEIGHT);

	SET_NETDEV_DEV(netdev, &intf->dev);
	ret = register_netdev(netdev);
	if (ret != 0) {
		netif_err(axdev, probe, netdev,
			  "couldn't register the device\n");
		goto out1;
	}	

	 /* usb_enable_autosuspend(udev); */
	
	return 0;
out1:
	netif_napi_del(&axdev->napi);
	usb_set_intfdata(intf, NULL);
out:
	free_netdev(netdev);
	return ret;
}

static void ax88179_disconnect(struct usb_interface *intf)
{
	struct ax_device *axdev = usb_get_intfdata(intf);

	usb_set_intfdata(intf, NULL);
	if (axdev) {
		ax_set_unplug(axdev);
		netif_napi_del(&axdev->napi);
		unregister_netdev(axdev->netdev);		
		free_netdev(axdev->netdev);
	}
}


static int ax88179_pre_reset(struct usb_interface *intf)
{
	struct ax_device *axdev = usb_get_intfdata(intf);
	struct net_device *netdev;

	if (!axdev)
		return 0;

	netdev = axdev->netdev;
	if (!netif_running(netdev))
		return 0;

	netif_stop_queue(netdev);
	napi_disable(&axdev->napi);
	smp_mb__before_atomic();
	clear_bit(AX88179_ENABLE, &axdev->flags);
	smp_mb__after_atomic();
	usb_kill_urb(axdev->intr_urb);
	cancel_delayed_work_sync(&axdev->schedule);

	return 0;
}

static int ax88179_post_reset(struct usb_interface *intf)
{
	struct ax_device *axdev = usb_get_intfdata(intf);
	struct net_device *netdev;

	if (!axdev)
		return 0;

	netdev = axdev->netdev;
	if (!netif_running(netdev))
		return 0;

	smp_mb__before_atomic();
	set_bit(AX88179_ENABLE, &axdev->flags);
	smp_mb__after_atomic();
	if (netif_carrier_ok(netdev)) {
		mutex_lock(&axdev->control);
		ax_start_rx(axdev);
		mutex_unlock(&axdev->control);
	}

	napi_enable(&axdev->napi);
	netif_wake_queue(netdev);
	usb_submit_urb(axdev->intr_urb, GFP_KERNEL);

	if (!list_empty(&axdev->rx_done))
		napi_schedule(&axdev->napi);

	return 0;
}

static int ax_system_resume(struct ax_device *axdev)
{
	struct net_device *netdev = axdev->netdev;

	netif_device_attach(netdev);

	if (netif_running(netdev) && (netdev->flags & IFF_UP)) {
		u16 reg16;
		u8 reg8;

		netif_carrier_off(netdev);		

		/* Power up ethernet PHY */
		reg16 = 0;
		ax88179_write_cmd_nopm(axdev, AX_ACCESS_MAC, AX_PHYPWR_RSTCTL,
				       2, 2, &reg16);
		usleep_range(1000, 2000);
		reg16 = AX_PHYPWR_RSTCTL_IPRL;
		ax88179_write_cmd_nopm(axdev, AX_ACCESS_MAC, AX_PHYPWR_RSTCTL,
				       2, 2, &reg16);
		msleep(200);		

		/* change clock */	
		ax88179_read_cmd_nopm(axdev, AX_ACCESS_MAC,  AX_CLK_SELECT,
				      1, 1, &reg8, 0);
		reg8 |= AX_CLK_SELECT_ACS | AX_CLK_SELECT_BCS;
		ax88179_write_cmd_nopm(axdev, AX_ACCESS_MAC, AX_CLK_SELECT,
				       1, 1, &reg8);
		msleep(100);

		/* Configure RX control register => start operation */
		reg16 = AX_RX_CTL_DROPCRCERR | AX_RX_CTL_START | AX_RX_CTL_AP |
			 AX_RX_CTL_AMALL | AX_RX_CTL_AB | AX_RX_CTL_IPE;		
		ax88179_write_cmd_nopm(axdev, AX_ACCESS_MAC, AX_RX_CTL,
				       2, 2, &reg16);
		
		smp_mb__before_atomic();
		set_bit(AX88179_ENABLE, &axdev->flags);
		smp_mb__after_atomic();		

		usb_submit_urb(axdev->intr_urb, GFP_NOIO);
	}

	return 0;
}

static int ax_runtime_resume(struct ax_device *axdev)
{
	struct net_device *netdev = axdev->netdev;

	if (netif_running(netdev) && (netdev->flags & IFF_UP)) {
		struct napi_struct *napi = &axdev->napi;

		napi_disable(napi);
		smp_mb__before_atomic();
		set_bit(AX88179_ENABLE, &axdev->flags);
		smp_mb__after_atomic();

		if (netif_carrier_ok(netdev)) {
			if (axdev->link) {
				ax_link_reset(axdev);
				ax_start_rx(axdev);
			} else {
				netif_carrier_off(netdev);
				ax_disable(axdev);
			}
		}
		
		napi_enable(napi);
		clear_bit(AX_SELECTIVE_SUSPEND, &axdev->flags);
		smp_mb__after_atomic();
		if (!list_empty(&axdev->rx_done)) {
			local_bh_disable();
			napi_schedule(&axdev->napi);
			local_bh_enable();
		}
		ax88179_write_cmd_nopm(axdev, AX_PHY_POLLING, 1, 0, 0, NULL);
		usb_submit_urb(axdev->intr_urb, GFP_NOIO);
	} else {
		clear_bit(AX_SELECTIVE_SUSPEND, &axdev->flags);
		smp_mb__after_atomic();
	}

	return 0;
}

static int ax_system_suspend(struct ax_device *axdev)
{
	struct net_device *netdev = axdev->netdev;
	int ret = 0;

	netif_device_detach(netdev);

	if (netif_running(netdev) && test_bit(AX88179_ENABLE, &axdev->flags)) {
		struct napi_struct *napi = &axdev->napi;
		u16 reg16;

		smp_mb__before_atomic();
		clear_bit(AX88179_ENABLE, &axdev->flags);
		smp_mb__after_atomic();
		usb_kill_urb(axdev->intr_urb);
		ax_disable(axdev);
		
		/* Disable RX path */
		ax88179_read_cmd_nopm(axdev, AX_ACCESS_MAC, AX_MEDIUM_STATUS_MODE,
				      2, 2, &reg16, 1);
		reg16 &= ~AX_MEDIUM_RECEIVE_EN;
		ax88179_write_cmd_nopm(axdev, AX_ACCESS_MAC,  AX_MEDIUM_STATUS_MODE,
				       2, 2, &reg16);

		ax88179_read_cmd_nopm(axdev, AX_ACCESS_MAC, AX_PHYPWR_RSTCTL,
				      2, 2, &reg16, 1);
		reg16 |= AX_PHYPWR_RSTCTL_IPRL;
		ax88179_write_cmd_nopm(axdev, AX_ACCESS_MAC, AX_PHYPWR_RSTCTL,
				       2, 2, &reg16);

		reg16 = AX_RX_CTL_STOP;
		ax88179_write_cmd_nopm(axdev, AX_ACCESS_MAC, AX_RX_CTL, 2, 2, &reg16);

		napi_disable(napi);
		cancel_delayed_work_sync(&axdev->schedule);
		napi_enable(napi);
	}

	return ret;
}

static int ax_runtime_suspend(struct ax_device *axdev)
{
	struct net_device *netdev = axdev->netdev;
	int ret = 0;

	set_bit(AX_SELECTIVE_SUSPEND, &axdev->flags);
	smp_mb__after_atomic();

	if (netif_running(netdev) && test_bit(AX88179_ENABLE, &axdev->flags)) {
		u16 reg16;

		if (netif_carrier_ok(netdev)) {
			ax88179_read_cmd_nopm(axdev, AX_ACCESS_MAC,
					      AX_RX_FREE_BUF_LOW, 
					      2, 2, &reg16, 1);
			if (reg16 != 0x067F) {
				ret = -EBUSY;
				goto out1;
			}
		}

		smp_mb__before_atomic();
		clear_bit(AX88179_ENABLE, &axdev->flags);
		smp_mb__after_atomic();
		usb_kill_urb(axdev->intr_urb);

		if (netif_carrier_ok(netdev)) {
			struct napi_struct *napi = &axdev->napi;

			napi_disable(napi);
			ax_stop_rx(axdev);
			napi_enable(napi);
		}

		/* Disable RX path */
		ax88179_read_cmd_nopm(axdev, AX_ACCESS_MAC,
				      AX_MEDIUM_STATUS_MODE, 2, 2, &reg16, 1);
		reg16 &= ~AX_MEDIUM_RECEIVE_EN;
		ax88179_write_cmd_nopm(axdev, AX_ACCESS_MAC,
				       AX_MEDIUM_STATUS_MODE, 2, 2, &reg16);

		/* Configure RX control register => stop operation */
		reg16 = AX_RX_CTL_STOP;
		ax88179_write_cmd_nopm(axdev, AX_ACCESS_MAC, AX_RX_CTL,
				       2, 2, &reg16);
	}

out1:
	return ret;
}

static int ax88179_suspend(struct usb_interface *intf, pm_message_t message)
{
	struct ax_device *axdev = usb_get_intfdata(intf);
	int ret;
	
	mutex_lock(&axdev->control);

	if (PMSG_IS_AUTO(message))		
		ret = ax_runtime_suspend(axdev);
	else
		ret = ax_system_suspend(axdev);

	mutex_unlock(&axdev->control);

	return ret;
}

static int ax88179_resume(struct usb_interface *intf)
{
	struct ax_device *axdev = usb_get_intfdata(intf);
	int ret;

	mutex_lock(&axdev->control);

	if (test_bit(AX_SELECTIVE_SUSPEND, &axdev->flags))
		ret = ax_runtime_resume(axdev);
	else
		ret = ax_system_resume(axdev);

	mutex_unlock(&axdev->control);
	return ret;
}

static int ax88179_reset_resume(struct usb_interface *intf)
{
	struct ax_device *axdev = usb_get_intfdata(intf);	

	clear_bit(AX_SELECTIVE_SUSPEND, &axdev->flags);
	mutex_lock(&axdev->control);
	ax_hw_init(axdev);
	mutex_unlock(&axdev->control);

	return 0;
}

/* table of devices that work with this driver */
static const struct usb_device_id ax88179_table[] = {
{
	USB_DEVICE(0x0b95, 0x1790),
},
{
	USB_DEVICE(0x0b95, 0x178a),
},
	{},	/* END */
};

MODULE_DEVICE_TABLE(usb, ax88179_table);

static struct usb_driver ax88179_driver = {
	.name =		MODULENAME,
	.id_table =	ax88179_table,
	.probe =	ax88179_probe,
	.disconnect =	ax88179_disconnect,
	.suspend =	ax88179_suspend,
	.resume =	ax88179_resume,
	.reset_resume =	ax88179_reset_resume,
	.pre_reset =	ax88179_pre_reset,
	.post_reset =	ax88179_post_reset,
	.supports_autosuspend = 1,
#if LINUX_VERSION_CODE >= KERNEL_VERSION(3,5,0)
	.disable_hub_initiated_lpm = 1,
#endif
};
module_usb_driver(ax88179_driver);
/* End of Driver operations */
