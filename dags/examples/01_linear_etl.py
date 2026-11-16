"""Example 1 — linear ETL (the classic shape).

Copy to dags/01_linear_etl.py to activate (only dags/*.py at the top level is
scanned). See docs/DAGS.md for the full key reference.
"""
DAG = {
    "name": "linear_etl",
    "schedule": "0 3 * * *",            # daily 03:00 — or "interval:120"
    "max_concurrency": 1,

    "tasks": [
        {
            "name": "extract",
            "func": "jobs.pipeline_demo.extract",
            "retries": 2,               # up to 3 runs total
            "retry_delay": 120,         # backoff: 2m, 4m
            "timeout": 300,
        },
        {
            "name": "transform",
            "func": "jobs.pipeline_demo.transform",
            "depends_on": ["extract"],
            "timeout": 600,
        },
        {
            "name": "load",
            "func": "jobs.pipeline_demo.load",
            "depends_on": ["transform"],
            "email_on_failure": True,   # alert ops if the warehouse write fails
        },
        {
            "name": "publish",          # shell task — venv's python first on PATH
            "command": "python -m jobs.pipeline_demo publish --date {ds}",
            "depends_on": ["load"],
        },
    ],
}
