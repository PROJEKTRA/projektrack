# E13 Invisible by default

Epic goal: no instance URL is ever indexed or easy to discover. This is a security requirement enforced in code and in CI; no admin setting can switch it off. It is never cut.

Estimates include implementation, tests, review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0985 | No-index headers, meta tags, robots.txt and no-referrer | Must | 8 | L1 |
| S-0986 | Minimal fingerprint and leak-free errors | Must | 6 | L1 |
| S-0987 | Secure shared links | Must | 8 | L1 |
| S-0988 | CI crawl test and external doctor check | Must | 10 | L1 |

Epic total: Must 32 h.

---

### S-0985 No-index headers, meta tags, robots.txt and no-referrer

| Field | Value |
|---|---|
| Persona | Security officer of a customer |
| Priority | Must |
| Estimate | 8 h (build 4, tests 3, review and docs 1) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0909 |
| Modules | core, deploy |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a customer's security officer, I want every response marked as not indexable and no URL leaked through referrers, so that our instance never appears in search engines.

```gherkin
Scenario: Instance is invisible to search engines
  Given any Projektrack installation (AMP, Docker, Kubernetes or SaaS)
  When a crawler requests the login page, a shared sign-off link, a PDF download, an unknown URL and robots.txt
  Then every response carries X-Robots-Tag noindex, nofollow, noarchive, nosnippet
  And robots.txt disallows all paths for all user agents
  And no response reveals the product name, version or framework before login
  And no page loads any resource from a third-party host

Scenario: Second layer and no previews
  Then every HTML page has a robots meta tag with noindex, nofollow, noarchive, nosnippet
  And no sitemap, Open Graph, Twitter card or schema.org data is served

Scenario: No referrer
  Then Referrer-Policy is no-referrer globally and external links carry rel="noopener noreferrer"

Scenario: Web server configs
  Then the shipped .htaccess, Nginx sample and Caddy config set the same headers for static files
```

---

### S-0986 Minimal fingerprint and leak-free errors

| Field | Value |
|---|---|
| Persona | Security officer of a customer |
| Priority | Must |
| Estimate | 6 h (build 3, tests 2, review and docs 1) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0985 |
| Modules | core, deploy |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a customer's security officer, I want the instance to reveal nothing about product, version or internals before login, so that scanners cannot fingerprint it.

```gherkin
Scenario: No fingerprint
  Then Server and X-Powered-By headers are removed, asset file names are content hashes, and the login page title is generic and configurable
  And there is no public version endpoint; the health endpoint returns only "OK" and can be IP-restricted

Scenario: Error pages
  Then 403, 404 and 500 pages and API errors never contain internal URLs, hostnames, paths or stack traces
```

Implementation note (20.11.2026): own error pages in the visitor's language (403, 404, 419, 429, 503, other 4xx and 5xx; also the maintenance page); every JSON error on any path is one plain sentence per status. A wrong method and OPTIONS answer like an unknown path. `GET /health` answers `OK` without session or cookies, `HEALTH_ALLOWED_IPS` limits it; the framework's `/up` page (which loaded fonts and scripts from other servers) is gone. Assets are named by content hash only, the favicon and logo are a neutral mark, the title before sign-in is the tenant's name or `BRAND_LOGIN_TITLE`, the version shows to administrators only, and `APP_DEBUG` is off in production whatever the environment says. Remaining by design: the page object of the sign-in page names its view (for example `auth/Login`), as every single-page client does.

---

### S-0987 Secure shared links

| Field | Value |
|---|---|
| Persona | Project lead sharing a document |
| Priority | Must |
| Estimate | 8 h (build 4, tests 3, review and docs 1) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0902, S-0907 |
| Modules | core |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a project lead, I want links I share with clients to be unguessable, expiring and revocable, so that documents reach only the intended people.

```gherkin
Scenario: Shared link properties
  When a project lead creates a shared link for a service report
  Then the token has at least 128 bits of entropy, expires after 30 days by default and can be revoked
  And an optional password can be set
  And each access is logged without storing the token in plain text
  And the response carries the noindex headers
```

---

### S-0988 CI crawl test and external doctor check

| Field | Value |
|---|---|
| Persona | Maintainer and instance operator |
| Priority | Must |
| Estimate | 10 h (build 6, tests 2, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0985, S-0986, S-0908 |
| Modules | CI, core |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a maintainer, I want CI to crawl a running instance and an operator command to check a live installation, so that the invisibility rules can never silently break.

```gherkin
Scenario: CI crawl
  When CI crawls a running instance anonymously and signed in
  Then the build fails if any response lacks X-Robots-Tag, if robots.txt does not disallow everything, or if any page contains indexable meta tags, Open Graph tags, version strings or third-party requests

Scenario: Doctor
  When the operator runs "projektrack doctor" against a live installation
  Then headers on pages and files, robots.txt and server signature are checked from outside
  And problems are reported in the admin UI
```
