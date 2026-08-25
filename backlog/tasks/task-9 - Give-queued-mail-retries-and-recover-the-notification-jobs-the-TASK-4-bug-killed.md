---
id: TASK-9
title: >-
  Give queued mail retries, and recover the notification jobs the TASK-4 bug
  killed
status: Done
assignee: []
created_date: '2026-08-25 17:04'
updated_date: '2026-08-25 17:21'
labels: []
dependencies:
  - TASK-4
priority: high
type: task
ordinal: 750
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Two related fixes to the production queue worker, both carried out on the server at the next deploy.

Background. All outbound mail goes through queued jobs (NotifyAdminsOfContactMessage, NotifyAdminsOfNewApplication, NotifyAskerOfAnswer, NotifyCreatorsOfNewQuestion) on the database queue driver. Nothing is sent until a worker picks the row up. On production that worker is a cron, configured in the hosting panel and written down nowhere in this repo:

    */5 * * * * cd /kunden/homepages/34/d527810786/htdocs/thrp-app && php8.4-cli artisan queue:work --stop-when-empty --max-time=280

That cadence is fine and --max-time=280 against a 300s interval correctly prevents overlap. The problem is what it does NOT pass.

1) No retries. queue:work defaults to --tries=1 and --backoff=0, and none of the four jobs declare their own $tries or $backoff, so the worker default governs all of them. One transient failure — a Resend blip, a DNS hiccup, a moment of network trouble — and that email is gone to failed_jobs permanently rather than retried a minute later. Nothing in these jobs is unsafe to run twice; the worst case is a duplicate notification to an admin.

2) A backlog of already-failed jobs. The TASK-4 bug made NotifyAdminsOfNewApplication throw at render time. With --tries=1 every one of those went straight to failed_jobs with no retry, for as long as the bug was live. The applications themselves were never lost — they are in the database and listed at /admin/applications — but nobody was ever told they had arrived, so an applicant may have volunteered and heard nothing back. Those job rows are still on production and can be replayed against the fixed code once TASK-4 is deployed.

Order matters: deploy the TASK-4 fix FIRST, then retry. Retrying beforehand just re-fails them and burns the attempts.

Inspect before acting — failed_jobs may hold unrelated wreckage that should be looked at rather than blindly replayed:

    php8.4-cli artisan queue:failed
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The production cron passes --tries=3 and --backoff=60, so a transient mail failure is retried rather than lost on the first attempt
- [ ] #2 queue:failed has been inspected and its contents reported before anything is retried
- [ ] #3 The TASK-4 fix is confirmed deployed to production before any retry is attempted
- [ ] #4 The failed NotifyAdminsOfNewApplication jobs have been retried and admins received the notifications that were lost, or the rows are documented as unrecoverable with the reason
- [ ] #5 Any responder application that was never acted on because its notification was lost is identified from /admin/applications and followed up
- [ ] #6 The production cron line is recorded in the repo (deploy.sh comment or README) so the queue worker is not invisible to anyone reading the codebase
<!-- AC:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
AC #2 evidence — queue:failed on production, 2026-08-25:

    2026-08-09 21:35:01  741aabc5-10cf-4d8e-b9ce-2f6433027809  database@default  App\Jobs\NotifyAdminsOfNewApplication
    2026-08-09 08:10:02  5c89e146-2416-446b-8ed4-f107cf35a7b4  database@default  App\Jobs\NotifyAdminsOfNewApplication
    2026-08-08 15:10:02  db316085-34c6-47af-ae08-201840813018  database@default  App\Jobs\NotifyAdminsOfNewApplication

Three rows, all the same job class. No unrelated wreckage — every failure on production is the TASK-4 render bug, so the whole table can be replayed rather than picked through.

Safe to retry. The failed row serialises the JOB, not the mailable, and the job's constructor ($email, $name, $message) was untouched by the TASK-4 fix — that change was inside handle(), where the mailable is built fresh. So these payloads deserialise cleanly into the fixed code. The job also carries plain strings rather than Eloquent models, so SerializesModels has nothing to re-resolve and there is no ModelNotFoundException risk even if the applicant's row has since changed.

These three are very likely the complete set: failed_jobs is never pruned (queue:prune-failed would have to be scheduled, and the schedule is empty — 'No scheduled tasks have been defined'). Nothing has failed since 2026-08-09, and since the bug was still live on 2026-08-25 when it was reproduced, that means no responder application has arrived since 9 August rather than that later failures were cleaned up. Worth sanity-checking the applied_at dates on /admin/applications against that.

AC #5 is now concrete, not hypothetical: three people applied on 8-9 August, roughly two and a half weeks ago, and no admin was ever told. Their applications are sitting pending at /admin/applications. Replaying the jobs notifies the admins, but it does not undo the silence the applicants experienced — they may need a direct apology as well as a review.
<!-- SECTION:NOTES:END -->
