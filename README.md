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

### Convert website CSVs

- In Access export CSVs for WebCatalogus.
	> Note: making a full export will mark those articles as exported in Access.
	> To prevent that, make the exports on a copy of the Access database.
	> Or, also import these CSVs into PrestaShop, otherwise future syncs will fail.
- Place all 3 files in `data/`.
- Run `./script/console ./script/command convert-website-csvs <timestamp>`.
	> Where `<timestamp>` is the `20240504_1705` part from `Artikelen_20240504_1705.csv`.
- A resulting CSV `LendEngineItems_<timestamp>_<convert-time>.csv` will be added in `data/`.

### Make full exports per table

- open database met shift-toets ingedrukt
- klik links bovenin op het pijltje naast 'Formulieren'
- kies voor 'Tabellen'
- scroll naar Verantwoordelijke, selecteer
- klik bovenin de ribbon op 'Externe gegevens'
- kies voor 'Tekstbestand'
- blader naar een map die je kan onthouden, en gebruik `.csv` als extensie
- _niet_ aanvinken van 'met opmaak en indeling'
- in 'Wizard Tekst exporteren'
	- gebruik specificatie
		- klik links onderin op 'Geavanceerd...'
		- klik rechts op 'Specificaties...'
		- selecteer 'CSV Exportspecificatie', en klik 'Openen'
	- handmatig opnieuw
		- niet standaard goed
			- 'Scheidingsteken veld': `,`
			- 'Decimaalsymbool': `.` (geen komma om conflicten te voorkomen net 'Scheidingsteken veld')
			- 'Codetabel': `Unicode (UTF-8)`
			- 'Datumvolgorde': `JMD`
		- standaard al goed
			- 'Gescheiden', niet 'Vaste breedte'
			- 'Tekstscheidingsteken': `"`
			- 'Taal': `Nederlands`
			- 'Datumscheidingsteken': `-`
			- 'Tijdscheidingsteken': `:`
			- 'Jaar met vier cijfers': aangevinkt
			- 'Voorloopnullen in datums': uitgevinkt
	- kies 'OK'
	- kies 'Volgende'
	- 'Ook veldnamen in de eerste rij': aanvinken
	- kies 'Volgende'
	- kies 'Voltooien'

#### Convert to contacts

- Place `Verantwoordelijke.csv`, `Plaats.csv`, and `Straat.csv` in `data/`.
- Run `./script/console ./script/command convert-contacts`.
- A resulting CSV `LendEngineContacts_<convert-time>.csv` will be added in `data/`.


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
