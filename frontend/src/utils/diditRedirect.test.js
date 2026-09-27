import test from 'node:test'
import assert from 'node:assert/strict'
import { safeDiditUrl, diditResultKind } from './diditRedirect.js'

test('only HTTPS Didit links without credentials or custom ports are accepted', () => {
  assert.equal(safeDiditUrl('https://verify.didit.me/el/session/abc'), 'https://verify.didit.me/el/session/abc')
  for (const url of ['javascript:alert(1)', 'http://verify.didit.me/a', 'https://verify.didit.me.evil.test/a', 'https://user@verify.didit.me/a', 'https://verify.didit.me:444/a', null]) assert.equal(safeDiditUrl(url), null)
})

test('result uses matching authenticated session state, not an untrusted status', () => {
  assert.equal(diditResultKind(null, 'a'), 'checking')
  assert.equal(diditResultKind({ status: 'Approved', verified: true, session_id: 'a' }, 'b'), 'mismatch')
  assert.equal(diditResultKind({ status: 'Approved', verified: false }, null), 'pending')
  assert.equal(diditResultKind({ status: 'Approved', verified: true, session_id: 'a' }, 'a'), 'success')
  for (const status of ['Declined', 'Expired', 'Abandoned', 'Kyc Expired']) assert.equal(diditResultKind({ status }, null), 'failure')
  for (const status of ['In Review', 'In Progress', 'Not Started']) assert.equal(diditResultKind({ status }, null), 'pending')
  for (const status of ['Awaiting User', 'Resubmitted']) assert.equal(diditResultKind({ status }, null), 'action')
})
