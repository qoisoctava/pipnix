"""Example 3 — command-only DAG (no Python func, no custom venv).

Commands run with the DAG's venv first on PATH; without a `venv` key they use
the system python3 (config: python_bin). `{ds}` is replaced with the logical
date. PN_* environment variables (PN_DAG, PN_TASK, PN_RUN_ID, PN_ATTEMPT) are
exported to every command.
"""
DAG = {
    "name": "shell_only",
    "schedule": "interval:30",

    "tasks": [
        {
            # everything is stdlib python — works on any shared host
            "name": "snapshot",
            "command": "python -c \"import os, json; print(json.dumps({'ds': os.environ.get('PN_RUN_ID'), 'task': os.environ.get('PN_TASK')}))\"",
            "timeout": 60,
        },
        {
            "name": "archive",
            # {ds} templating; exit code decides success/failure
            "command": "test -n \"{ds}\" && echo \"archiving {ds}\"",
            "depends_on": ["snapshot"],
        },
    ],
}
