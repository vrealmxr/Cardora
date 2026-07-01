export const getOrderRole = (order, currentUserId) => {
  if (!order || !currentUserId) return 'viewer'
  if (Number(order.buyerId) === Number(currentUserId)) return 'buyer'
  if (Number(order.sellerId) === Number(currentUserId)) return 'seller'
  return 'viewer'
}

export const getOrderStage = (order, role = 'viewer', locale = 'el') => {
  const isEnglish = locale === 'en'
  const hasShipped = Boolean(order?.shippedAt || order?.deliveredAt || order?.releasedAt)
  const hasDelivered = Boolean(order?.deliveredAt || order?.releasedAt)

  if (order?.statusKey === 'released') {
    return {
      key: 'released',
      label: isEnglish ? 'Released to seller' : 'Αποδεσμεύτηκε',
      tone: 'success',
      summary: isEnglish
        ? 'Funds have been released to the seller Stripe account.'
        : 'Τα χρήματα έχουν αποδεσμευτεί προς το Stripe account του πωλητή.',
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
        : 'Η Cardora εξετάζει την παραγγελία πριν από οποιαδήποτε αποδέσμευση ή recovery.',
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
    if (!hasShipped) {
      if (role === 'seller') {
        return {
          key: 'needs_shipping',
          label: isEnglish ? 'Needs shipping' : 'Πρέπει να σταλεί',
          tone: 'warning',
          summary: isEnglish
            ? 'The DHL label is created automatically after payment. Hand over the parcel so DHL can scan it.'
            : 'Το DHL label δημιουργείται αυτόματα μετά την πληρωμή. Παράδωσε το δέμα ώστε να γίνει το πρώτο scan από τη DHL.',
          actionRequired: true,
        }
      }

      return {
        key: 'waiting_for_seller',
        label: isEnglish ? 'Waiting for shipment' : 'Αναμένει αποστολή',
        tone: 'info',
        summary: isEnglish
          ? 'Payment is protected while the seller prepares the DHL handoff.'
          : 'Η πληρωμή παραμένει προστατευμένη όσο ο πωλητής ετοιμάζει την παράδοση στη DHL.',
        actionRequired: false,
      }
    }

    if (!hasDelivered) {
      return {
        key: 'in_transit',
        label: isEnglish ? 'In transit' : 'Σε μεταφορά',
        tone: 'info',
        summary: isEnglish
          ? 'Shipment updates come directly from DHL. Funds stay on hold until delivery is confirmed.'
          : 'Οι ενημερώσεις έρχονται απευθείας από τη DHL. Τα χρήματα μένουν σε hold μέχρι να επιβεβαιωθεί η παράδοση.',
        actionRequired: false,
      }
    }

    if (role === 'buyer') {
      return {
        key: 'awaiting_confirmation',
        label: isEnglish ? 'Confirm delivery' : 'Επιβεβαίωσε την παράδοση',
        tone: 'warning',
        summary: isEnglish
          ? 'DHL marked the order as delivered. Confirm everything is OK to release funds now, otherwise auto-release runs in 2 days.'
          : 'Η DHL έδειξε ότι η παραγγελία παραδόθηκε. Επιβεβαίωσε ότι όλα είναι ΟΚ για άμεσο release, αλλιώς το auto-release τρέχει σε 2 ημέρες.',
        actionRequired: true,
      }
    }

    return {
      key: 'awaiting_buyer_confirmation',
      label: isEnglish ? 'Waiting for buyer confirmation' : 'Αναμένει επιβεβαίωση αγοραστή',
      tone: 'info',
      summary: isEnglish
        ? 'DHL marked the order as delivered. Funds will release after buyer confirmation or after the 2-day protection window.'
        : 'Η DHL έδειξε ότι η παραγγελία παραδόθηκε. Τα χρήματα θα αποδεσμευτούν μετά την επιβεβαίωση του αγοραστή ή μετά το 2ήμερο παράθυρο προστασίας.',
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
  const shippedCompleted = Boolean(order?.shippedAt || order?.deliveredAt || order?.releasedAt)
  const deliveredCompleted = Boolean(order?.deliveredAt || order?.releasedAt)
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
      current: shippedCompleted && !deliveredCompleted,
    },
    {
      key: 'delivered',
      label: isEnglish ? 'Delivered' : 'Παραδόθηκε',
      completed: deliveredCompleted,
      current: deliveredCompleted && !releasedCompleted,
    },
    {
      key: 'released',
      label: isEnglish ? 'Released' : 'Αποδεσμεύτηκε',
      completed: releasedCompleted,
      current: releasedCompleted,
    },
  ]
}
