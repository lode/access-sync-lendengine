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
	- Artikelen_\<timestamp>
	- ArtikelTypes_\<timestamp>
	- Merken_\<timestamp>
- [Export Access tables](/docs/Export%20Access%20tables.md)
	- Artikel
	- Lid
	- LidStatus
	- LidType
	- Melding
	- MeldingSoort
	- Onderdeel
	- Plaats
	- Straat
	- Verantwoordelijke
- Place all exported files in `data/`.

### 2. Run scripts

Run each command with `./script/console ./script/command <commandName> <optionalExtraArguments>`.

You can run `./script/console ./script/command <commandName> --help` to get more information about any extra arguments.

| Data | Command | Contents |
| --- | --- | --- |
| Get insight | `insight` | Contacts without email address and contacts which share an email address |
| Contacts | `convert-contacts` | Contact basics: name, email, phone, address, etc. |
| Items | `convert-website-csvs` + `csvTimestamp` | Item basics: name, code, category, brand, price, etc. |
| Parts | `gather-extra-data-item-parts` | Count, description |
| Memberships | `gather-extra-data-memberships` + `membershipId` + `membershipPrice` | Contact <> Subscription, period |
| Contacts extras | `gather-extra-data-contacts` | Membership number and contact created |
| Contact notes | `gather-extra-data-contact-notes` | Messages ("meldingen") |
| Item custom fields | `gather-extra-data-item-custom-fields` + `customFieldId` | Messages ("meldingen") |
| Items extras | `gather-extra-data-items` | Item created |

Output files `LendEngine*.csv` & `LendEngine*.sql` will be added in `data/`.

### 3. Import CSVs in Lend Engine admin

The CSVs from the above scripts (`LendEngine*.csv` for items & contacts) can be imported via Lend Engine's CSV import admin.

- Import items via Admin > Items > Bulk update (/admin/import/items/)
	- Copy the contents of the output of the `convert-website-csvs` command
	- Copy quotes along (copy raw content, not the selection when opening in a spreadsheet program) to support newlines
	- Enable "Create new items where code is not found"
- Import contacts via Admin > Settings > Import contacts (/admin/import/contacts/)
	- Copy the contents of the output of the `convert-contacts` command
	- Don't copy the header row along
	- Import 10 contacts at once because of performance issues

### 4. Import SQLs via Lend Engine support

The SQLs from the above scripts (`LendEngine*.sql`) can't be imported via Lend Engine admin.
Contact Lend Engine support and ask to import the SQLs for you.


## To Do

- Make non-reservable category configurable
- Improve item text fields
- Part mutations (waiting for LE implementation)
- Maintenance
- Loan history
- Convert staff contacts to get admin access


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
