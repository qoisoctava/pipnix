"""Example 4 — retries with backoff against a flaky dependency.

jobs.pipeline_demo.flaky_api deliberately fails its first attempt, so after a
trigger this DAG visibly demonstrates: failed → retrying → success, and the
attempt counter in the run view. `email_on_failure` only fires when ALL
attempts are exhausted.
"""
DAG = {
    "name": "retry_backoff",
    "schedule": "0 */6 * * *",          # every 6 hours

    "tasks": [
        {
            "name": "fetch",
            "func": "jobs.pipeline_demo.flaky_api",
            "retries": 3,
            "retry_delay": 60,          # waits: 1m, 2m, 3m (linear backoff)
            "timeout": 30,              # per attempt; hung sockets reaped as timed_out
            "email_on_failure": True,   # needs alerts.enabled=true in config.php
        },
        {
            "name": "notify_ok",
            "command": "echo 'fetch ok on {ds}'",
            "depends_on": ["fetch"],
        },
    ],
}
