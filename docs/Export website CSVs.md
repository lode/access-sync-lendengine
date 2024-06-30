# Convert website CSVs

- In Access export CSVs for WebCatalogus.
	> Note: making a full export will mark those articles as exported in Access.
	> To prevent that, make the exports on a copy of the Access database.
	> Or, also import these CSVs into PrestaShop, otherwise future syncs will fail.
- Place all 3 files in `data/`.
- Run `./script/console ./script/command convert-website-csvs <timestamp>`.
	> Where `<timestamp>` is the `20240504_1705` part from `Artikelen_20240504_1705.csv`.
- A resulting CSV `LendEngineItems_<timestamp>_<convert-time>.csv` will be added in `data/`.
