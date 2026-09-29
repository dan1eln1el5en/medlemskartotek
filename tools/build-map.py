#!/usr/bin/env python3
"""
Builds assets/map-data.json – the vector map for the member list.

Plot outlines come from the official Danish cadastre (Matriklen) via
Dataforsyningen/DAWA, free and without login. Run again after adding roads:

    python3 tools/build-map.py

A grund named e.g. "SV7" is matched to the plot at "Svanevænget 7".
"""
import json
import math
import os
import urllib.parse
import urllib.request

POSTNR = "3630"
ROADS = {            # grund prefix -> road name
    "SV": "Svanevænget",
    "KT": "Kysttoften",
    "KV": "Kystvej",
    "KS": "Kystsvinget",
    "SS": "Svanestien",
}
CONTEXT_MARGIN = 60  # metres of neighbouring plots/roads drawn around the area
SRID = "25832"       # UTM 32N: metres, so the map is to scale

API = "https://api.dataforsyningen.dk"
OUT = os.path.join(os.path.dirname(__file__), "..", "assets", "map-data.json")


def get(path, **params):
    url = f"{API}/{path}?" + urllib.parse.urlencode(params)
    with urllib.request.urlopen(url, timeout=60) as r:
        return json.load(r)


def rings(geom):
    if geom["type"] == "Polygon":
        return geom["coordinates"]
    if geom["type"] == "MultiPolygon":
        return [ring for poly in geom["coordinates"] for ring in poly]
    return []


def lines(geom):
    if geom["type"] == "LineString":
        return [geom["coordinates"]]
    if geom["type"] == "MultiLineString":
        return geom["coordinates"]
    return []


def main():
    # 1. Association plots: address -> cadastral plot (several addresses can share one plot).
    plots, seen = {}, set()
    for prefix, road in ROADS.items():
        for a in get("adgangsadresser", vejnavn=road, postnr=POSTNR):
            if not a.get("jordstykke"):
                print(f"  {prefix}{a['husnr']:>4}  {road} {a['husnr']}  (no cadastral plot – skipped)")
                continue
            ejerlav, matr = a["jordstykke"]["ejerlav"]["kode"], a["jordstykke"]["matrikelnr"]
            key = (ejerlav, matr)
            if key not in plots:
                js = get(f"jordstykker/{ejerlav}/{urllib.parse.quote(matr)}", format="geojson", srid=SRID)
                plots[key] = {
                    "codes": [], "addresses": [],
                    "matrikel": f"{matr}, {a['jordstykke']['ejerlav']['navn']}",
                    "rings": rings(js["geometry"]),
                }
            seen.add(key)
            plots[key]["codes"].append(f"{prefix}{a['husnr']}")
            plots[key]["addresses"].append(f"{road} {a['husnr']}")
            print(f"  {prefix}{a['husnr']:>4}  {road} {a['husnr']}  matr. {matr}")
    plots = list(plots.values())

    pts = [p for plot in plots for ring in plot["rings"] for p in ring]
    min_e = min(p[0] for p in pts) - CONTEXT_MARGIN
    max_e = max(p[0] for p in pts) + CONTEXT_MARGIN
    min_n = min(p[1] for p in pts) - CONTEXT_MARGIN
    max_n = max(p[1] for p in pts) + CONTEXT_MARGIN
    polygon = json.dumps([[[min_e, min_n], [max_e, min_n], [max_e, max_n], [min_e, max_n], [min_e, min_n]]])

    # 2. Neighbouring plots and roads for context.
    context = [
        {"rings": rings(f["geometry"])}
        for f in get("jordstykker", polygon=polygon, srid=SRID, format="geojson")["features"]
        if (f["properties"]["ejerlavkode"], f["properties"]["matrikelnr"]) not in seen
    ]
    roads = [
        {"name": f["properties"]["navn"], "lines": lines(f["geometry"])}
        for f in get("vejstykker", polygon=polygon, srid=SRID, format="geojson")["features"]
        if f.get("geometry")
    ]

    # 3. Project to SVG coordinates (1 unit = 1 m, y flipped).
    def xy(p):
        return f"{p[0] - min_e:.1f} {max_n - p[1]:.1f}"

    def area_path(rs):
        return " ".join("M" + " L".join(xy(p) for p in r[:-1]) + "Z" for r in rs)

    def line_path(ls):
        return " ".join("M" + " L".join(xy(p) for p in l) for l in ls)

    def label(ls):
        """Midpoint + angle of the longest segment run, for road names."""
        l = max(ls, key=len)
        mid = l[len(l) // 2 - 1: len(l) // 2 + 1] if len(l) > 1 else [l[0], l[0]]
        (x1, y1), (x2, y2) = mid
        ang = math.degrees(math.atan2(-(y2 - y1), x2 - x1))
        if ang > 90: ang -= 180
        if ang < -90: ang += 180
        return {"x": round((x1 + x2) / 2 - min_e, 1), "y": round(max_n - (y1 + y2) / 2, 1), "angle": round(ang, 1)}

    def centre(rs):
        r = max(rs, key=len)[:-1]
        return {"x": round(sum(p[0] for p in r) / len(r) - min_e, 1), "y": round(max_n - sum(p[1] for p in r) / len(r), 1)}

    data = {
        "source": "Matriklen / Dataforsyningen (CC BY 4.0)",
        "width": round(max_e - min_e, 1),
        "height": round(max_n - min_n, 1),
        "plots": [
            {"codes": p["codes"], "addresses": p["addresses"], "matrikel": p["matrikel"],
             "d": area_path(p["rings"]), "c": centre(p["rings"])}
            for p in plots if p["rings"]
        ],
        "context": [area_path(c["rings"]) for c in context if c["rings"]],
        "roads": [
            {"name": r["name"], "d": line_path(r["lines"]), "label": label(r["lines"])}
            for r in roads if r["lines"]
        ],
    }
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, separators=(",", ":"))
    print(f"{len(data['plots'])} plots, {len(data['context'])} context plots, {len(data['roads'])} roads "
          f"-> {os.path.relpath(OUT)} ({os.path.getsize(OUT) // 1024} KB)")


if __name__ == "__main__":
    main()
