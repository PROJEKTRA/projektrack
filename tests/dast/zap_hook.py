# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Signs the scanner in with the session of the test and shows it every page,
# because the pages are built in the browser and have no links for a spider.
import json


def zap_started(zap, target):
    with open("/zap/wrk/scan.json", encoding="utf-8") as file:
        scan = json.load(file)
    if scan["cookie"]:
        zap.replacer.add_rule(
            "session", True, "REQ_HEADER", False, "Cookie", scan["cookie"]
        )
    for url in scan["urls"]:
        zap.core.access_url(url, False)
