export const getOrderRole = (order, currentUserId) => {
  if (!order || !currentUserId) return 'viewer'
  if (Number(order.buyerId) === Number(currentUserId)) return 'buyer'
  if (Number(order.sellerId) === Number(currentUserId)) return 'seller'
  return 'viewer'
}

export const getOrderStage = (order, role = 'viewer', locale = 'el') => {
  const isEnglish = locale === 'en'
  const hasTracking = Boolean(order?.trackingNumber)

  if (order?.statusKey === 'released') {
    return {
      key: 'released',
      label: isEnglish ? 'Released to seller' : 'Αποδεσμεύτηκε',
      tone: 'success',
      summary: isEnglish
        ? 'Funds have been released to the seller Stripe account.'
        : 'Τα χρήματα αποδεσμεύτηκαν προς το Stripe account του πωλητή.',
      actionRequired: false,
    }
  }

  if (order?.statusKey === 'refunded') {
    return {
      key: 'refunded',
      label: isEnglish ? 'Refunded' : 'Έγινε refund',
      tone: 'danger',
      summary: isEnglish
        ? 'The order was refunded and the protected flow is closed.'
        : 'Η παραγγελία έγινε refund και το protected flow έκλεισε.',
      actionRequired: false,
    }
  }

  if (order?.statusKey === 'cancelled') {
    return {
      key: 'cancelled',
      label: isEnglish ? 'Cancelled' : 'Ακυρώθηκε',
      tone: 'muted',
      summary: isEnglish ? 'The order was cancelled.' : 'Η παραγγελία ακυρώθηκε.',
      actionRequired: false,
    }
  }

  if (order?.statusKey === 'disputed') {
    return {
      key: 'disputed',
      label: isEnglish ? 'In dispute' : 'Σε dispute',
      tone: 'warning',
      summary: isEnglish
        ? 'Cardora is reviewing this order before any release or recovery.'
        : 'Η Cardora εξετάζει την παραγγελία πριν από επόμενη αποδέσμευση ή recovery.',
      actionRequired: false,
    }
  }

  if (order?.statusKey === 'pending_payment') {
    return {
      key: 'pending_payment',
      label: isEnglish ? 'Awaiting payment' : 'Αναμένει πληρωμή',
      tone: 'muted',
      summary: isEnglish
        ? 'Stripe has not confirmed payment yet.'
        : 'Το Stripe δεν έχει επιβεβαιώσει ακόμη την πληρωμή.',
      actionRequired: false,
    }
  }

  if (order?.statusKey === 'paid_pending_release') {
    if (role === 'seller' && !hasTracking) {
      return {
        key: 'needs_shipping',
        label: isEnglish ? 'Needs shipping' : 'Πρέπει να σταλεί',
        tone: 'warning',
        summary: isEnglish
          ? 'The buyer has paid. Ship the order and add tracking.'
          : 'Ο αγοραστής πλήρωσε. Στείλε την παραγγελία και πρόσθεσε tracking.',
        actionRequired: true,
      }
    }

    if (role === 'buyer' && !hasTracking) {
      return {
        key: 'waiting_for_seller',
        label: isEnglish ? 'Waiting for shipment' : 'Αναμένει αποστολή',
        tone: 'info',
        summary: isEnglish
          ? 'The payment is protected while the seller prepares shipment.'
          : 'Η πληρωμή προστατεύεται όσο ο πωλητής ετοιμάζει την αποστολή.',
        actionRequired: false,
      }
    }

    if (role === 'buyer') {
      return {
        key: 'awaiting_confirmation',
        label: isEnglish ? 'Confirm when received' : 'Επιβεβαίωσε όταν παραλάβεις',
        tone: 'warning',
        summary: isEnglish
          ? 'The order has shipped. Confirm receipt to release funds.'
          : 'Η παραγγελία έχει σταλεί. Επιβεβαίωσε παραλαβή για να αποδεσμευτούν τα χρήματα.',
        actionRequired: true,
      }
    }

    return {
      key: 'awaiting_buyer_confirmation',
      label: isEnglish ? 'Waiting for buyer confirmation' : 'Αναμένει επιβεβαίωση αγοραστή',
      tone: 'info',
      summary: isEnglish
        ? 'The order has shipped. Funds stay on hold until buyer confirmation or auto-release.'
        : 'Η παραγγελία στάλθηκε. Τα χρήματα μένουν σε hold μέχρι επιβεβαίωση αγοραστή ή auto-release.',
      actionRequired: false,
    }
  }

  return {
    key: 'processing',
    label: isEnglish ? 'Processing' : 'Σε επεξεργασία',
    tone: 'muted',
    summary: isEnglish ? 'The order is being processed.' : 'Η παραγγελία βρίσκεται σε επεξεργασία.',
    actionRequired: false,
  }
}

export const buildOrderTimeline = (order, locale = 'el') => {
  const isEnglish = locale === 'en'
  const paidCompleted = order?.statusKey !== 'pending_payment'
  const shippedCompleted = Boolean(order?.trackingNumber || order?.releasedAt || order?.buyerConfirmedAt)
  const confirmedCompleted = Boolean(order?.buyerConfirmedAt || order?.releasedAt)
  const releasedCompleted = Boolean(order?.releasedAt || order?.statusKey === 'released')

  return [
    {
      key: 'paid',
      label: isEnglish ? 'Paid' : 'Πληρώθηκε',
      completed: paidCompleted,
      current: paidCompleted && !shippedCompleted,
    },
    {
      key: 'shipped',
      label: isEnglish ? 'Shipped' : 'Στάλθηκε',
      completed: shippedCompleted,
      current: shippedCompleted && !confirmedCompleted,
    },
    {
      key: 'confirmed',
      label: isEnglish ? 'Confirmed' : 'Επιβεβαιώθηκε',
      completed: confirmedCompleted,
      current: confirmedCompleted && !releasedCompleted,
    },
    {
      key: 'released',
      label: isEnglish ? 'Released' : 'Αποδεσμεύτηκε',
      completed: releasedCompleted,
      current: releasedCompleted,
    },
  ]
}
