# Access <> Lend Engine

Sync data from [SpeelotheekSoftware's Access](https://speelotheeksoftware.nl/) to [Lend Engine](https://www.lend-engine.com/).

There is also a [document on how to setup Lend Engine for toy libraries](https://docs.google.com/document/d/1hRl2P_GZhFMwi5PnTcKprZJZ9NPpgkrJyAQ-OSP50BY/edit?usp=sharing).
This discusses migrating data and making the correct settings.


## Install

- Install: [Docker Desktop on Mac](https://docs.docker.com/desktop/install/mac-install/), [Docker Engine is enough for Linux](https://docs.docker.com/engine/install/ubuntu/)
    - Mac: no need to sign in with a Docker account when the installer asks you to.
    - Mac: under 'Settings' -> 'General' disable 'SBOM indexing'.
    - Mac: use `./script/*` for managing docker instead of the control panel of Docker Desktop for Mac.
    - Linux: follow [Docker post-installation](https://docs.docker.com/engine/install/linux-postinstall/) to manage docker without root.

- Run `./script/setup`
- Run `./script/server`
- Run `./script/console composer install`


## Usage

### 1. Export data from SpeelotheekSoftware's Access

- [Export Access tables](/docs/Export%20Access%20tables.md)
	- Artikel
	- ArtikelStatus
	- ArtikelStatusLogging
	- ArtikelType
	- ArtikelUitleenDuur
	- KasboekType
	- Lid
	- LidStatus
	- LidType
	- Medewerker
	- Melding
	- MeldingSoort
	- Merk
	- Onderdeel
	- OnderdeelMutatie
	- Plaats
	- Straat
	- Tarief
	- TariefEenheid
	- TariefPeriode
	- Verantwoordelijke
- [Export website CSVs](/docs/Export%20website%20CSVs.md) (alternative if previous export doesn't work)
	- Artikelen_\<csvTimestamp>
	- ArtikelTypes_\<csvTimestamp>
	- Merken_\<csvTimestamp>
- Place all exported files in `data/`.
- Copy article photos in `data/photos/`, using their code as file name, e.g. `B42.jpg`.

### 2. Run scripts

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
| Parts mutations | `gather-extra-data-item-part-mutations` | Count missing/broken, explanation. |
| Images | `gather-extra-data-item-images photos` | Item images (SQL and converted image files). |
| Memberships | `gather-extra-data-memberships` | Contact <> Subscription, period. |
| Item status | `gather-extra-data-item-location` | Locations ("status") for items. |
| Notes | `gather-extra-data-notes` | Messages ("meldingen") for contacts and items. |
| Contact notes | `gather-extra-data-contact-notes` | Specifics ("bijzonderheden") for contacts. |
| Items extras | `gather-extra-data-items` | Item created, show on catalogus. |
| Contacts extras | `gather-extra-data-contacts` | Contact created. |
| Contacts obfuscation | `obfuscate-contacts [timestamp]` | Obfuscate contact migration so it can be used to test with. |

Here's all the commands after each other.
Run them one-by-one as some are not needed for your use case, and some have interactive output.

```bash
./script/console ./script/command insight
./script/console ./script/command convert-contacts
./script/console ./script/command convert-items
./script/console ./script/command convert-website-csvs [csvTimestamp]
./script/console ./script/command gather-extra-data-item-parts
./script/console ./script/command gather-extra-data-item-part-mutations
./script/console ./script/command gather-extra-data-item-images photos
./script/console ./script/command gather-extra-data-memberships
./script/console ./script/command gather-extra-data-item-location
./script/console ./script/command gather-extra-data-notes
./script/console ./script/command gather-extra-data-contact-notes
./script/console ./script/command gather-extra-data-items
./script/console ./script/command gather-extra-data-contacts
./script/console ./script/command obfuscate-contacts [timestamp]
```

Output files `LendEngine*.csv` & `LendEngine*.sql` will be added in `data/`.

Don't forget to update duplicate email addresses in `LendEngine_01_Contacts_[timestamp].csv` before importing.

### 3. Import CSVs in Lend Engine admin

The CSVs from the above scripts (`LendEngine*.csv` for items & contacts) can be imported via Lend Engine's CSV import admin.

- Import items (Admin » Items » Bulk update)
	- Copy the contents of `LendEngine_02_Items_[timestamp].csv` **OR** `LendEngine_02_ItemsAlternative_[timestamp].csv`.
	- Copy quotes along (copy raw content using text editor, not the selection when opening in a spreadsheet program) to support newlines.
	- Enable "Create new items where code is not found".
- Import contacts (Admin » Settings » Import contacts)
	- Copy the contents of `LendEngine_01_Contacts_[timestamp].csv` **OR** `LendEngine_01_Contacts_[timestamp]_obfuscated_[timestamp].csv`.

### 4. Import SQLs via Lend Engine support

The SQLs from the above scripts (`LendEngine*.sql`) can't be imported via Lend Engine admin.

Create a zip archive of all `LendEngine*.sql` files and the `export_<timestamp>/` directory with migrated photos.
You can upload to WeTransfer and contact Lend Engine support (support@lend-engine.com) and ask to import this for you.

Wait until this is done before changing things in Lend Engine yourself to prevent conflicts.

### 5. Cleanup

Don't forget to delete the exported files locally as they contain sensitive user data.

### 6. Configure Lend Engine

- Rename migrated membership types (Admin » Settings » Membership types) to something that makes more sense. Delete the ones not used.


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
- If you see a warning that you don't have a generic type for yet: create a generic type (Admin » Settings » Check out prompts / Check in prompts / Item fields).
- Afterwards (or when you're importing a warning that you already have a generic type for): connect a generic type to a specific item (Check in/out / Custom fields).


## Contributing

### Development

- A new script can be added to `src/command/` and `script/command`.
- If using a new export table from Access add it to `src/specification/`.
- Mention new scripts (and optionally new tables) to the lists in the readme.

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
