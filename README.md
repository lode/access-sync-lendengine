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

- [Export Access tables](/docs/Export%20Access%20tables.md)
	- Artikel
	- ArtikelType
	- ArtikelUitleenDuur
	- Lid
	- LidStatus
	- LidType
	- Melding
	- MeldingSoort
	- Merk
	- Onderdeel
	- Plaats
	- Straat
	- Verantwoordelijke
- [Export website CSVs](/docs/Export%20website%20CSVs.md) (alternative if previous export doesn't work)
	- Artikelen_\<csvTimestamp>
	- ArtikelTypes_\<csvTimestamp>
	- Merken_\<csvTimestamp>
- Place all exported files in `data/`.
- Copy article photos in `data/photos/`, using their code as file name, e.g. `B42.jpg`.

### 2. Configure Lend Engine

- Create a membership type (Admin » Settings » Membership types » Add a membership type) to migrating existing memberships. Lookup its id in the edit url.
- Create a custom item field (Admin » Settings » Item fields » Add a custom field) to migrate messages ("meldingen"). Create a "Type" of "Multiple lines of text". Lookup its id in the edit url.

### 3. Run scripts

Run each command with `./script/console ./script/command <commandName> <optionalExtraArguments>`.

You can run `./script/console ./script/command <commandName> --help` to get more information about any extra arguments.

Each script migrates a part of the data, you can choose which to run and do a manual migration for the rest.

| Data | Command | Contents |
| --- | --- | --- |
| Get insight | `insight` | Contacts without email address and contacts which share an email address.<br>Alter email addresses in the following export to prevent duplicates when importing. |
| Contacts | `convert-contacts` | Contact basics: name, email, phone, address, membership number, etc. |
| Items | `convert-items` | Item basics: name, code, category, brand, price, etc. |
| Items alternative | `convert-website-csvs [csvTimestamp]` | Item basics, alternative method with webcatalogus CSVs. |
| Parts | `gather-extra-data-item-parts` | Count, description. |
| Images | `gather-extra-data-item-images photos` | Item images (SQL and converted image files). |
| Memberships | `gather-extra-data-memberships [membershipId] [membershipPrice]` | Contact <> Subscription, period.<br>Use the id and price from the membership type created in step 2.<br>If you have multiple different memberships run this script multiple times with each subset of the export and different membership types. |
| Contact notes | `gather-extra-data-contact-notes` | Messages ("meldingen") and specifics ("bijzonderheden") for contacts. |
| Item custom fields | `gather-extra-data-item-custom-fields [customFieldId]` | Messages ("meldingen") for items.<br>Use the id from the custom item field created in step 2. |
| Contacts extras | `gather-extra-data-contacts` | Contact created. |
| Items extras | `gather-extra-data-items` | Item created, show on catalogus. |

Output files `LendEngine*.csv` & `LendEngine*.sql` will be added in `data/`.

### 4. Import CSVs in Lend Engine admin

The CSVs from the above scripts (`LendEngine*.csv` for items & contacts) can be imported via Lend Engine's CSV import admin.

- Import items (Admin » Items » Bulk update)
	- Copy the contents of `LendEngineItems_[timestamp].csv` (the output of the `convert-items` **OR** `convert-website-csvs` command).
	- Copy quotes along (copy raw content using text editor, not the selection when opening in a spreadsheet program) to support newlines.
	- Enable "Create new items where code is not found".
- Import contacts (Admin » Settings » Import contacts)
	- Copy the contents of `LendEngineContacts_[timestamp].csv` (the output of the `convert-contacts` command).

### 5. Import SQLs via Lend Engine support

The SQLs from the above scripts (`LendEngine*.sql`) can't be imported via Lend Engine admin.

Create a zip archive of all `LendEngine*.sql` files and the `export_<timestamp>/` directory with migrated photos.
You can upload to WeTransfer and contact Lend Engine support (support@lend-engine.com) and ask to import this for you.

Wait until this is done before changing things in Lend Engine yourself to prevent conflicts.

### 6. Cleanup

Don't forget to delete the exported files locally as they contain sensitive user data.


## Manual migration

### Employee status

Go through all staff in the contacts (Admin » Contacts / Members) and mark them as staff or administrator.

### Item warnings

In Access item warnings are unique for each item.
In Lend Engine item warnings are created as predefined generic types, and used at specific items.
Thus when migrating you need to decide which warnings you want to use, create those, and then use those in items.

Here's how the concepts are mapped between Access and Lend Engine:

| Access | Lend Engine |
| --- | --- |
| "Uitlenen" | Check out prompt |
| "Inname" | Check in prompt |
| "Controle" | Check in prompt |
| "Uitleen-info" | Item field or Custom field |

Go through all items to manually migrate item warnings.

- Open an article in Access and go to the warning tab ("Waarschuwingen").
- Open the same in Lend Engine (Admin » Items » Browse items).
- If you see a warning that you don't have a generic type yet: create a generic type (Admin » Settings » Check out prompts / Check in prompts / Item fields).
- Afterwards, or when you see a warning that you already have a generic type for: connect a generic type to a specific item (Check in/out / Custom fields).

### Part mutations

At this moment the best way to migrate this is by adjusting the part description to something alike "(1 missing)".

Later, this might be done automatically.

### Item status (maintenance, loan status, current location, loan history)

In Access this is an _item status_ is "Onderhoud" / "Uitgeleend" / "Afgekeurd" / etc.
In Lend Engine this is an _item location_ ("Repair" / etc.) and _loan status_ (e.g. "On loan").

- Create the item locations (Admin » Settings » Locations » Add an item location) you want to have.
- Also create an temporary item location for items currently on loan (e.g. "Uitgeleend in Access").
  This is needed as loan history can't (yet) be migrated.
  This item location can be cleaned up once all items are checked in and all loans are actual loans in Lend Engine.
- In Access filter on the special statuses.
- In Lend Engine list items (Admin » Items » Browse items).
- Open each item from the Access list in Lend Engine and change location (Move / Service) based on its status in Access.

Later, this might be done automatically.


## To Do

- Part mutations (needs LE implementation and adjusted migration afterwards)
- Maintenance
- Loan status / current location / loan history


## Contributing

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

### Development

- A new script can be added to `src/command/` and `script/command`.
- If using a new export table from Access add it to `src/specification/`.
