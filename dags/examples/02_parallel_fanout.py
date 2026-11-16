"""Example 2 — parallel fan-out + join.

fetch_a/b/c have no dependencies on each other, so the tick dispatches them
concurrently (each DAG's max_concurrency caps how many at once; the global
tick.max_launches_per_tick caps across all DAGs). `join` waits for all three.
"""
DAG = {
    "name": "parallel_fanout",
    "schedule": "interval:60",
    "max_concurrency": 3,               # allow all fetchers to run together

    "tasks": [
        {"name": "fetch_a", "func": "jobs.pipeline_demo.fetch_a", "timeout": 120},
        {"name": "fetch_b", "func": "jobs.pipeline_demo.fetch_b", "timeout": 120},
        {"name": "fetch_c", "func": "jobs.pipeline_demo.fetch_c", "timeout": 120},
        {
            "name": "join",
            "func": "jobs.pipeline_demo.join",
            "depends_on": ["fetch_a", "fetch_b", "fetch_c"],
        },
    ],
}
