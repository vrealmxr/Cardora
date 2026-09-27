export function safeDiditUrl(raw) {
  try {
    const url = new URL(raw)
    return url.protocol === 'https:' && url.hostname === 'verify.didit.me' && !url.port && !url.username && !url.password ? url.href : null
  } catch { return null }
}

// Only feed this function authenticated API state, never Didit's query-string status.
export function diditResultKind(state, returnedSessionId) {
  if (!state) return 'checking'
  if (returnedSessionId && state.session_id !== returnedSessionId) return 'mismatch'
  if (state.status === 'Approved' && state.verified) return 'success'
  if (['Declined', 'Expired', 'Abandoned', 'Kyc Expired'].includes(state.status)) return 'failure'
  if (['Resubmitted', 'Awaiting User'].includes(state.status)) return 'action'
  return 'pending'
}
