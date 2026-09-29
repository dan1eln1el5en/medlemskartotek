=== Stenøgård Medlems Manager ===
Contributors: daniel
Version: 1.5
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

== Changelog ==
= 1.5 =
* New Member List page (replaces "All Data" and "Export Emails").
* Co-owner fields on owners.
* E-mail/phone/ownership columns on the Grunde and Grundejere admin lists.
* Danish translation.
* CSV re-import now updates contact info on existing owners.
* Trashed/deleted owners no longer show as owners of a grund.
