# Local environment setup — Dana-Farber Impact (Drupal)

This guide is for engineers who are setting up the project **from scratch** on a laptop: no PHP, no Composer, and no prior Drupal tooling on the machine. The site runs in **[DDEV](https://ddev.com/)**, which uses Docker, so your local stack matches the team’s without installing PHP or MariaDB directly on macOS.

**Time to first working site (with a database dump):** about 30–60 minutes, mostly downloads.

**What you will have when done**

- Docker running a local Drupal11 site- HTTPS URL: `https://df-impact.ddev.site`
- Drush available as `ddev drush` from the `drupal/` directory
- Optional: this repo opened in Cursor with a clear “do this next” checklist

---

## 1. What you are installing (big picture)


| Piece                                     | Role                                                                                     |
| ----------------------------------------- | ---------------------------------------------------------------------------------------- |
| **Git**                                   | Clone the repository                                                                     |
| **Docker Desktop** (or compatible engine) | Runs Linux containers for web + database                                                 |
| **DDEV**                                  | Starts the Drupal stack; wires hostnames; runs Composer/Drush **inside** the container   |
| **This repo**                             | Code under `drupal/` (Composer project + `web/` docroot); DDEV config in `drupal/.ddev/` |


You do **not** need to install PHP, MariaDB, or Composer on the host. Use `ddev composer` and `ddev drush` instead.

**Important path detail:** DDEV is configured under `**drupal/`**, not the repository root. Almost every command in this doc assumes your terminal’s current directory is `.../df-impact/drupal` (after you clone).

---

## 2. Prerequisites by operating system

### 2.1 macOS (most common on this team)

1. **Apple Silicon (M1/M2/M3) or Intel** — both work; use the ARM or Intel build of Docker as appropriate.
2. Enough free disk space: **Docker + images + repo** often needs **15 GB+** free; more if you keep many images.

### 2.2 Windows

Use **WSL2** with Docker Desktop’s WSL2 integration, then install DDEV **inside WSL2** (Ubuntu is typical). Do not use legacy “Docker Toolbox” or Hyper-V-only setups unless you already maintain them. Official guidance: [DDEV Windows installation](https://docs.ddev.com/en/stable/users/install/docker-installation-windows-wsl2/).

### 2.3 Linux

Install Docker Engine (or Docker Desktop for Linux), then DDEV per [DDEV Linux installation](https://docs.ddev.com/en/stable/users/install/docker-installation-linux/).

---

## 3. Step-by-step installation

Complete these in order.

### Step A — Install Git

- **macOS:** Install Xcode Command Line Tools (`xcode-select --install`) or install Git via [git-scm.com](https://git-scm.com/).
- **Windows:** [Git for Windows](https://git-scm.com/download/win).
- **Linux:** Use your distribution’s `git` package.

Verify:

```bash
git --version
```

### Step B — Install Docker Desktop

1. Download and install [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or your org-approved equivalent).
2. Start Docker and wait until it reports **running** (whale icon idle on macOS/Windows).
3. Allocate enough resources in Docker settings if your machine is tight on RAM (4 GB minimum for the stack; 8 GB for the host is more comfortable).

Verify:

```bash
docker version
```

If this errors with “cannot connect to Docker daemon,” fix Docker first — DDEV will not work until Docker is healthy.

### Step C — Install DDEV

Follow the official installer for your OS: [DDEV installation](https://docs.ddev.com/en/stable/users/install/).

Verify:

```bash
ddev version
```

### Step D — Clone the repository

Choose a folder where you keep code (example: `~/Projects`).

```bash
cd ~/Projects
git clone <YOUR_TEAM_GIT_URL> df-impact
cd df-impact
```

Use the real clone URL your team uses (HTTPS or SSH). If SSH, ensure your SSH key is added to the Git host first.

### Step E — Open the project in Cursor (optional but recommended)

1. In Cursor: **File → Open Folder…**
2. Select the `**df-impact`** folder (repository root), not only `drupal/`.

**Tip:** You can paste the short prompt in [§10](#10-prompt-to-hand-cursor) into a new agent chat so Cursor walks through verification steps with you.

### Step F — Start DDEV (from `drupal/`)

```bash
cd drupal
ddev start
```

**First run** may take several minutes while Docker pulls images.

Expected URL (from `drupal/.ddev/config.yaml`, project name `df-impact`):

- **HTTPS:** `https://df-impact.ddev.site`
- **HTTP:** `http://df-impact.ddev.site`

DDEV may create or update `**web/sites/default/settings.ddev.php`** when you start the project. That file is environment-specific and is not the source of truth in Git; it is normal for it to be generated locally.

Open the site in a browser:

```bash
ddev launch
```

Until the database is installed or imported, you may see an installation error or Drupal installer — that is expected (see Step H).

### Step G — Install PHP dependencies with Composer (inside DDEV)

Still in `**drupal/**`:

```bash
ddev composer install
```

This reads `composer.json` / `composer.lock` and populates `vendor/`. You need network access and enough disk space.

If Composer fails with memory errors (rare), your team can adjust DDEV/PHP limits; ask in Slack before changing global PHP on the host.

### Step H — Database: you must have *something* in MariaDB

Drupal does not ship a production database in Git. You need **one** of:


| Approach                                          | When to use                                                                    |
| ------------------------------------------------- | ------------------------------------------------------------------------------ |
| **A. Import a SQL dump from the team**            | Fastest way to get a real site (recommended for most developers)               |
| **B. Fresh `drush site:install` + config import** | Advanced; you still need a matching database state to match production content |


#### H1 — Import a database dump (typical)

Obtain a `.sql`, `.sql.gz`, or `.tar.gz` dump from a teammate or secure internal location.

Place the file somewhere on your machine, then from `**drupal/`**:

```bash
ddev import-db --file=/absolute/path/to/dump.sql.gz
```

Use your real path. DDEV accepts several formats; see `ddev help import-db`.

Then rebuild caches and check status:

```bash
ddev drush cr
ddev drush status
```

You want `**Drupal bootstrap**` to show **Successful**.

#### H2 — Configuration sync directory

Exported configuration in this project lives under:

`drupal/web/sites/default/files/sync/`

After the database exists, align configuration with the export:

```bash
ddev drush cim -y
ddev drush cr
```

If `drush cim` complains that `$settings['config_sync_directory']` is not defined, add a **local-only** override:

1. At the bottom of `drupal/web/sites/default/settings.php`, uncomment the block that includes `settings.local.php` (lines that look like `if (file_exists(...settings.local.php)) { include ... }`). Coordinate with the team before committing any change to `settings.php`; many developers keep this uncommented in their own branch or use a team-approved local patch.
2. Create `drupal/web/sites/default/settings.local.php` for overrides. Treat it like any local-only file: do not put secrets in Git; confirm with your team whether this path is ignored or committed.
3. Set the sync path relative to the **Drupal root** (the `web/` directory — the folder that contains `index.php`):

```php
<?php

$settings['config_sync_directory'] = 'sites/default/files/sync';
```

Confirm with `ddev drush status` that **Configuration sync** points at the directory that contains your exported `*.yml` files.

Ask the team if unsure — some environments may already define this in generated DDEV settings after `ddev start`.

#### H3 — One-time login

```bash
ddev drush uli
```

Paste the URL into your browser to log in as the admin user from the dump.

---

## 4. Daily workflow (after setup)

Always start Docker, then:

```bash
cd /path/to/df-impact/drupal
ddev start
ddev launch
```

Common commands (run from `**drupal/**`):


| Task                       | Command                   |
| -------------------------- | ------------------------- |
| Drush                      | `ddev drush …`            |
| Composer                   | `ddev composer …`         |
| Shell inside web container | `ddev ssh`                |
| Import DB again            | `ddev import-db --file=…` |
| Stop project               | `ddev stop`               |


---

## 5. Mail and outbound HTTP

Outgoing email in local DDEV is usually handled by **Mailpit** (fake inbox). Your team’s DDEV version may expose a UI URL when you run `ddev start`; check the command output.

If modules call external APIs, you may need VPN or mocks depending on environment — confirm with the team.

---

## 6. Repository conventions that affect you

- `**docs/AGENT_HANDOFF.md`** — Short engineer orientation (migrations, config paths, troubleshooting).
- `**DRUPAL_REBUILD_PLAN.md`** — Deeper product/technical context.
- **Generated files:** Do not commit aggregated assets under `files/css/`, `files/js/`, `files/php/`, `files/styles/` — they are ignored by design.
- `**testing/`** and `**standups/`** are intentionally not shared via Git (local only).

---

## 7. Optional: migration tooling (Python)

Some WordPress → Drupal migration steps use scripts under `drupal/migration-data/`. If you need to regenerate JSON for migrations, install Python 3 per your OS and follow comments in `process_wp_data.py`. This is **not** required just to browse a site when you already have a database dump.

---

## 8. Troubleshooting

### “Cannot connect to the Docker API” / `docker.sock`

- Start **Docker Desktop** and wait until it is fully running.
- Retry `ddev start`.

### `ddev start` fails or ports are busy

- Stop other local stacks that use port 80/443, or ask DDEV to diagnose: `ddev describe`.
- On rare corporate VPNs, `.ddev.site` DNS may fail — see [DDEV troubleshooting](https://docs.ddev.com/en/stable/users/usage/troubleshooting/).

### Drush “bootstrap failed” or wrong PHP

Always run `**ddev drush`** from `**drupal/`**, not host `drush`.

### Composer is slow or times out

- Confirm network/VPN.
- Retry `ddev composer install`.

### Site loads but images are missing

Production media may rely on **Stage File Proxy** or copied files under `files/` — ask the team for the expected local media setup.

### HTTPS certificate warnings

Browsers may warn on `*.ddev.site` the first time; DDEV uses local trust mechanisms — follow prompts or use `ddev trust` / documentation for your DDEV version.

---

## 9. Checklist (copy for onboarding tickets)

- Git installed
- Docker Desktop running
- DDEV installed (`ddev version`)
- Repository cloned
- `cd drupal && ddev start` succeeds
- `ddev composer install` succeeds
- Database imported (or site installed per team procedure)
- `ddev drush status` → bootstrap successful
- `ddev drush cim -y` and `ddev drush cr` (when using team config export)
- `ddev launch` opens the site
- `ddev drush uli` works

---

## 10. Prompt to hand Cursor

Copy everything inside the block into a new Cursor chat (with this repo as the workspace) if you want the agent to verify your machine step-by-step:

```text
Read docs/LOCAL_ENVIRONMENT_SETUP.md and docs/AGENT_HANDOFF.md. My repo root is this workspace. Guide me through verifying Docker and DDEV, then from the drupal/ directory run ddev start, ddev composer install, and ddev drush status. If bootstrap fails, explain the most likely cause (missing DB vs config path) and the next command to run. Do not ask me to install PHP or Composer on the host; use ddev composer and ddev drush only.
```

---

## 11. Technical reference (for support)


| Item                        | Value / location                 |
| --------------------------- | -------------------------------- |
| DDEV project name           | `df-impact`                      |
| DDEV config                 | `drupal/.ddev/config.yaml`       |
| Docroot                     | `drupal/web`                     |
| PHP (in container)          | 8.3                              |
| Database (in container)     | MariaDB 11.8                     |
| Composer                    | v2 inside DDEV                   |
| Drupal core (from Composer) | 11.x (`drupal/core-recommended`) |


---

*Document version: written for engineers cloning the df-impact repository. Update this file when the team changes DDEV project name, PHP version, or standard database onboarding procedure.*