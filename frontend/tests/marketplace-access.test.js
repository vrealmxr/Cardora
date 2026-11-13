import test from 'node:test'
import assert from 'node:assert/strict'
import { marketplaceGateMessage } from '../src/utils/marketplaceAccess.js'

test('buying depends on can_buy independently of seller activation', () => {
  const access = { can_buy: true, can_sell: false, can_bid: true, can_join_draws: false,
    is_marketplace_ready: false, blocking_message: 'Seller verification required',
    participation_blocking_message: 'Participation verification required' }
  for (const locale of ['el', 'en']) {
    assert.equal(marketplaceGateMessage({ id: 1 }, access, 'buy', locale), null)
    assert.equal(marketplaceGateMessage({ id: 1 }, access, 'sell', locale), access.blocking_message)
    assert.equal(marketplaceGateMessage({ id: 1 }, access, 'bid', locale), null)
    assert.equal(marketplaceGateMessage({ id: 1 }, access, 'draw', locale), access.participation_blocking_message)
    assert.ok(marketplaceGateMessage({ id: 1 }, { ...access, can_bid: false }, 'bid', locale))
    assert.equal(marketplaceGateMessage({ id: 1 }, access, 'trade', locale), access.blocking_message)
    assert.ok(marketplaceGateMessage({ id: 1 }, { ...access, can_buy: false, is_marketplace_ready: true }, 'buy', locale), 'explicit buyer denial must not be overridden by seller status')
    assert.equal(marketplaceGateMessage({ id: 1 }, { ...access, can_sell: true }, 'sell', locale), null)
  }
})
