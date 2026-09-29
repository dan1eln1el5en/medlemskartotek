=== Stenøgård Medlems Manager ===
Contributors: daniel
Version: 1.10
License: GPLv2 or later
Description: Admin interface for grunde (properties) and grundejere (owners) in grundejerforeningen Stenøgård.
Custom plugin skrevet af Daniel L. Nielsen for grundejerforeningen Stenøgård

== Overview ==
* Grunde and Grundejere are separate records. Each grund points to one grundejer
  (set on the grund), so "who owns this grund" and "what does this person own" always agree.
* A grundejer can have an optional co-owner (medejer, e.g. spouse) with own e-mail and phone.
* Medlemsliste (top-level menu): search, sort, switch between "per grund" and "per grundejer",
  copy all e-mails in the current list (for Bcc), and download the list for Excel.
* Admin language follows each user's profile language (English / Danish).
  Danish strings: languages/am-da_DK.l10n.php

== Updates from GitHub ==
The site gets updates from https://github.com/dan1eln1el5en/medlemskartotek via the
Git Updater plugin (git-updater.com). To release a new version: raise "Version:" in
medlemskartotek.php, commit and push to main. The update then shows under Plugins.

== Map ==
The "Kort" view draws the plots from the official cadastre (Matriklen, via Dataforsyningen).
A grund named "SV7" is matched to Svanevænget 7 (SV = Svanevænget, KT = Kysttoften,
KV = Kystvej, KS = Kystsvinget, SS = Svanestien).
To add roads, edit ROADS in tools/build-map.py and run:  python3 tools/build-map.py
The map data (assets/map-data.json) holds only public cadastral data, no member data.

== Changelog ==
= 1.10 =
* Map now includes Kystsvinget (KS) – the whole association area is covered (126 plots).

= 1.9 =
* Map now includes Kystvej (KV) and Svanestien (SS).

= 1.8 =
* New "Kort" (map) view: hover a plot to see owner, e-mail and phone; click to open the owner.
* Search highlights matching plots on the map.

= 1.7 =
* One "Medlemsliste" menu: Grunde, Grundejere, Tilføj grund, Tilføj grundejer, Importér CSV.
* The old paged Grunde/Grundejere lists now open the member list in the matching view.
* Edit screens link back to the member list; trash is reachable from the bottom of the list.

= 1.6 =
* Updates can be installed from GitHub via Git Updater.

= 1.5 =
* New Member List page (replaces "All Data" and "Export Emails").
* Co-owner fields on owners.
* E-mail/phone/ownership columns on the Grunde and Grundejere admin lists.
* Danish translation.
* CSV re-import now updates contact info on existing owners.
* Trashed/deleted owners no longer show as owners of a grund.
