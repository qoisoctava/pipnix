"""Example 5 — two independent branches converge on one summary.

Branch A (db_export → validate) and branch B (stats_pull) share nothing;
`summarize` depends on both, so it only runs when BOTH branches succeeded.
If db_export fails, validate becomes upstream_failed and summarize never
runs — the other branch still finishes normally.
"""
DAG = {
    "name": "branch_join",
    "schedule": "0 5 * * *",            # daily 05:00

    "tasks": [
        # branch A
        {"name": "db_export", "func": "jobs.pipeline_demo.extract", "retries": 1},
        {"name": "validate",  "func": "jobs.pipeline_demo.transform", "depends_on": ["db_export"]},

        # branch B — independent of branch A
        {"name": "stats_pull", "func": "jobs.pipeline_demo.fetch_b"},

        # convergence
        {
            "name": "summarize",
            "func": "jobs.pipeline_demo.summarize",
            "depends_on": ["validate", "stats_pull"],
        },
    ],
}
