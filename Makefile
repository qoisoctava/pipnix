.PHONY: unit e2e ui ui-actions pass-change check tick clean-test dist

unit:            ## cron/logger unit tests (no DB)
	php tests/php/cron_test.php

check: unit      ## php syntax + unit tests
	@find app -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
	@echo "php lint ok"

e2e:             ## full integration test (needs dev MySQL on PN_MYSQL_PORT)
	bash tests/e2e.sh

ui:              ## web console smoke test (auth + every page)
	bash tests/ui_smoke.sh

ui-actions:      ## web console write-action tests (editor, import, trigger, venv, users)
	bash tests/ui_actions.sh

pass-change:     ## forced password change on weak install passwords
	bash tests/pass_change.sh

tick:            ## one scheduler tick
	php app/scheduler.php

clean-test:      ## remove e2e leftovers
	rm -f app/config.php dags/e2e_flow.py dags/e2e_fail.py jobs/e2e_demo.py
	rm -rf dags/__pycache__ jobs/__pycache__

dist:            ## build a release zip from the tracked files (dist/pipnix-v<version>.zip)
	@mkdir -p dist && \
	V=$$(grep -o "PN_VERSION', '[^']*" app/bootstrap.php | cut -d"'" -f3) && \
	git archive --format=zip --prefix="pipnix/" -o "dist/pipnix-v$$V.zip" HEAD && \
	echo "built dist/pipnix-v$$V.zip"
