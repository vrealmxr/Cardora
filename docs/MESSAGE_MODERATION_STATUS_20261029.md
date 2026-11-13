# Message moderation: corrected filter active in production

## Current release — 29 October 2026

The owner subsequently authorized completing local validation and re-enabling the corrected filter. That release is now deployed. The temporary disable described below is historical.

Only `app/Services/MessageModerationService.php` was deployed. Production and local SHA-256: `59f685797c1b40d7d157a2e70707cec866e0621d7d152aaf59a216a5bafb82b1`.

- Local PHPUnit: **529 tests, 3,217 assertions passed**. Includes the 8 legacy unit tests, a permanent marketplace regression corpus, 400 issue-number combinations, overlapping Unicode spans, partial masking and repeated/empty calls.
- Local API integration: **86 checks passed**, including 13 new moderation checks through authenticated message creation, recipient retrieval and editing. SQLite was in memory; HTTP, email, jobs and Stripe were faked. No real messages or external transactions occurred.
- Production CLI: **523 direct-service checks passed** against the deployed class; no database writes. Site HTTP health check returned 200. This is not a browser message submission into a real conversation.
- Existing stored messages, manually restored messages, and messages sent during the temporary bypass were not reprocessed.

Private deployment stage: `/home/u912666299/cardora-private/message-moderation-corrected-20261029`. Its `install.php --rollback` restores the **disabled** version using hash guards. The stage contains a backup, manifest and `verification.json`.

### Behavior

Address recognition requires specific address structure/context; ordinary item numbers are not addresses. Explicit address spans are hidden while surrounding order details remain readable. Calendar dates and narrowly labelled item identifiers are exempt from phone detection, without exempting other content in the message. An identifier followed by additional phone digits is not exempt.

Semantic rules retain word boundaries instead of searching substrings in an entire concatenated message. Common product words such as origami, mountain, instant, mobile/headphones and Greek descriptions of soft covers are regression tested. Explicit contact requests, addresses, off-platform channels, insults and violent threats remain covered. Accented Greek and the legacy mixed-script/obfuscated examples are tested. Overlapping byte spans are merged before Unicode masking.

These are deterministic rules, not a guarantee of perfect interpretation of every ambiguous title, quotation or deliberate evasion. New confirmed false positives should become regression fixtures before changing a rule.

### Reproduce locally

From the backend, with its Composer dependencies installed:

```text
php vendor/bin/phpunit --no-configuration --bootstrap tests/bootstrap-moderation.php tests/Unit/MessageModerationServiceTest.php
```

`CARDORA_TEST_VENDOR_AUTOLOAD` can point to an existing compatible vendor autoloader. This bootstrap loads no application environment, database or network services. Fixtures live in `backend/tests/Fixtures/MessageModeration/`. Local API harness and deployment evidence remain in `D:\Cardora\RuntimeValidation\message-moderation-fix-20261029`.

## Historical temporary production override

The owner first requested that message filtering be disabled on the live site while the replacement was validated locally. It remained disabled until the subsequent explicit reactivation request and successful checks above.

Production `app/Services/MessageModerationService.php` now returns the trimmed original body, no masked body, clean status, empty flags, zero score, and no review request. This applies to new messages and edits using this service, including offer notes. It does not unmask or otherwise modify historical records. Authentication, conversation authorization and message validation remain unchanged.

Only this service file was deployed, using a hash-guarded atomic replacement and private backup. No database writes, actual test messages, notifications, RunPod changes or paid services were used.

- Before SHA-256: `8de923b7919add406613808c26d0be53d0d36b598667d24d3e89100966c1d448`
- Disabled SHA-256: `719e2f4abe9723ccdd38f7990edda0f02db10e2ab3b05021cd33696e92c5a92b`
- Private server stage: `/home/u912666299/cardora-private/message-moderation-disabled-20261029`
- Restore mechanism: `install.php --rollback` in that stage. This restores the faulty old rules, so do not use it to reactivate the corrected filter.
- Live CLI verification: 87 inputs passed through unchanged (apart from existing whitespace trimming), with no masking and no database writes. This is a direct service check, not a browser submission to a real customer conversation.

## Initial local investigation (superseded by release above)

The repository service contains a separate local correction. The original false positive came from treating a compact sequence of letters, a small number and more letters as an address. The new address rules use original text, explicit address context and bounded spans. Item identifiers and calendar dates have narrow exceptions; broad number and ordinary photo-request triggers were removed. Overlapping masked spans are merged, and Greek accent normalization was corrected.

Current standalone regression corpus: 487/487 passed, including the reported comic issue messages, 400 issue-number combinations, card numbers, prices, reference IDs, addresses, contact details and partial-span masking. The existing local API integration harness passed 73 checks before the final two canonical-token corrections. These counts do not imply universal detection or readiness to re-enable production; legacy unit expectations and further review remain to be reconciled.

Local evidence and reproducible scripts: `D:\Cardora\RuntimeValidation\message-moderation-fix-20261029`.

Research reviewed [Presidio](https://github.com/data-privacy-stack/presidio) and [scrubadub](https://github.com/LeapBeyond/scrubadub). Neither was installed. Context-aware recognizers inform this targeted PHP correction; neither repository provides a drop-in validated Greek marketplace moderation policy.

Reactivation was completed in the dedicated release above. Preserve messages the owner has already corrected manually.
