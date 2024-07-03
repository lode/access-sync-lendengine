# Access <> Lend Engine

Sync data from [SpeelotheekSoftware's Access](https://speelotheeksoftware.nl/) to [Lend Engine](https://www.lend-engine.com/).


## Install

- Install: [Docker Desktop on Mac](https://docs.docker.com/desktop/install/mac-install/), [Docker Engine is enough for Linux](https://docs.docker.com/engine/install/ubuntu/)
    - Mac: no need to sign in with a Docker account when the installer asks you to.
    - Mac: under 'Settings' -> 'General' disable 'SBOM indexing'.
    - Mac: use `./script/*` for managing docker instead of the control panel of Docker Desktop for Mac.
    - Linux: follow [Docker post-installation](https://docs.docker.com/engine/install/linux-postinstall/) to manage docker without root.

- Run `./script/setup`
- Run `./script/server`


## Usage

### 1. Export data from SpeelotheekSoftware's Access

- [Export website CSVs](/docs/Export%20website%20CSVs.md)
- [Export Access tables](/docs/Export%20Access%20tables.md)
- Place all exported files in `data/`.

### 2. Run scripts

- Insight:                       `./script/console ./script/command insight`
- Convert contacts:              `./script/console ./script/command convert-contacts`
- Convert website CSVs:          `./script/console ./script/command convert-website-csvs <timestamp>`
- Gather extra contacts data:    `./script/console ./script/command gather-extra-data-contacts`
- Gather extra items data:       `./script/console ./script/command gather-extra-data-items`
- Gather extra parts data:       `./script/console ./script/command gather-extra-data-item-parts`
- Gather extra memberships data: `./script/console ./script/command gather-extra-data-memberships`

Output files `LendEngine*.csv` & `LendEngine*.sql` will be added in `data/`.

### 3. Import item & member data via Lend Engine's CSV import admin

This can be used for the CSVs output from the scripts

### 4. Ask Lend Engine support to import for extra data via SQLs

Some scripts output SQL instead of CSV


## Development

### Usage after first setup

- Start server: `./script/server`
- See [the script/ directory](/script/README.md) for more commands

### Connect to the database

Connect to the database from outside Docker:

- hostname: `localhost`
- port: see `SQL_PORT_EXTERNAL` in `docker.env`
- username/password: see values in `docker.env`

For managing databases:

- username: `root`
- password: `root-secret`
