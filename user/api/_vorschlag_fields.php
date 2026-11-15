<?php
/**
 * GENERIERT von import-tools/gen_vorschlag_fields.py aus dem Eingabeformular (eingabe.html.twig) - nicht von Hand aendern.
 * Feldkatalog der Vorschlagspruefung (Umbau A, Stufe 2): Abschnitte und Felder, die die Gaertnereien erfassen und
 * die ein Editor feldweise in die plants.db uebernehmen kann. Schluessel = Tabelle.Spalte.
 */

function pdb_vorschlag_fields(): array
{
    static $c = null;
    if ($c === null) {
        $c = json_decode(<<<'JSON'
[
 {
  "key": "taxonomy",
  "title": "Taxonomie",
  "fields": [
   {
    "key": "plants.genus",
    "table": "plants",
    "col": "genus",
    "label": "Gattung",
    "type": "text",
    "taxonomy": true
   },
   {
    "key": "plants.species",
    "table": "plants",
    "col": "species",
    "label": "Art",
    "type": "text",
    "taxonomy": true
   },
   {
    "key": "plants.cultivar",
    "table": "plants",
    "col": "cultivar",
    "label": "Sorte / Kultivar",
    "type": "text",
    "taxonomy": true
   },
   {
    "key": "plants.subspecies",
    "table": "plants",
    "col": "subspecies",
    "label": "Unterart",
    "type": "text",
    "taxonomy": true
   },
   {
    "key": "plants.ecotype",
    "table": "plants",
    "col": "ecotype",
    "label": "Ökotyp",
    "type": "text",
    "taxonomy": true
   }
  ]
 },
 {
  "key": "texts",
  "title": "Texte",
  "fields": [
   {
    "key": "plants.notes",
    "table": "plants",
    "col": "notes",
    "label": "Notizen",
    "type": "textarea"
   },
   {
    "key": "plants.description_short",
    "table": "plants",
    "col": "description_short",
    "label": "Kurzbeschreibung",
    "type": "textarea"
   },
   {
    "key": "plants.description_long",
    "table": "plants",
    "col": "description_long",
    "label": "Langbeschreibung",
    "type": "textarea"
   },
   {
    "key": "plants.care_instructions",
    "table": "plants",
    "col": "care_instructions",
    "label": "Pflanzhinweise",
    "type": "textarea"
   },
   {
    "key": "plants.meta_text",
    "table": "plants",
    "col": "meta_text",
    "label": "Metatext",
    "type": "textarea"
   }
  ]
 },
 {
  "key": "names",
  "title": "Namen",
  "fields": [
   {
    "key": "plant_names.name_de",
    "table": "plant_names",
    "col": "name_de",
    "label": "Deutscher Name",
    "type": "text"
   },
   {
    "key": "plant_names.name_en",
    "table": "plant_names",
    "col": "name_en",
    "label": "English Name",
    "type": "text"
   },
   {
    "key": "plant_names.name_fr",
    "table": "plant_names",
    "col": "name_fr",
    "label": "Nom français",
    "type": "text"
   },
   {
    "key": "plant_names.name_it",
    "table": "plant_names",
    "col": "name_it",
    "label": "Nome italiano",
    "type": "text"
   },
   {
    "key": "plant_names.registration_name",
    "table": "plant_names",
    "col": "registration_name",
    "label": "Registrierungsname",
    "type": "text"
   },
   {
    "key": "plant_names.exhibition_name",
    "table": "plant_names",
    "col": "exhibition_name",
    "label": "Ausstellungsname",
    "type": "text"
   },
   {
    "key": "plant_names.trade_name",
    "table": "plant_names",
    "col": "trade_name",
    "label": "Handelsname",
    "type": "text"
   }
  ]
 },
 {
  "key": "site",
  "title": "Standort",
  "fields": [
   {
    "key": "plant_site.hardiness_zone",
    "table": "plant_site",
    "col": "hardiness_zone",
    "label": "Winterhärtezone",
    "type": "select",
    "options": [
     "Zone 1 (unter -45.6 °C)",
     "Zone 2a (-45.5 °C)",
     "Zone 2b (-42.7 °C)",
     "Zone 3a (-39.9 °C)",
     "Zone 3b (-37.2 °C)",
     "Zone 4a (-34.4 °C)",
     "Zone 4b (-31.6 °C)",
     "Zone 5a (-28.8 °C)",
     "Zone 5b (-26.1 °C)",
     "Zone 6a (-23.3 °C)",
     "Zone 6b (-20.4 °C)",
     "Zone 7a (-17.7 °C)",
     "Zone 7b (-14.9 °C)",
     "Zone 8a (-12.2 °C)",
     "Zone 8b (-9.4 °C)",
     "Zone 9a (-6.6 °C)",
     "Zone 9b (-3.8 °C)",
     "Zone 10a (-1.1 °C)",
     "Zone 10b (+1.7 °C)",
     "Zone 11 (über +4.5 °C)"
    ]
   },
   {
    "key": "plant_site.hardiness_temp_min",
    "table": "plant_site",
    "col": "hardiness_temp_min",
    "label": "Min. Temp. °C",
    "type": "number"
   },
   {
    "key": "plant_site.hardiness_temp_max",
    "table": "plant_site",
    "col": "hardiness_temp_max",
    "label": "Max. Temp. °C",
    "type": "number"
   },
   {
    "key": "plant_site.light_requirement",
    "table": "plant_site",
    "col": "light_requirement",
    "label": "Lichtbedarf",
    "type": "multi",
    "options": [
     "vollsonnig",
     "sonnig",
     "halbschattig",
     "schattig",
     "absonnig"
    ]
   },
   {
    "key": "plant_site.moisture_requirement",
    "table": "plant_site",
    "col": "moisture_requirement",
    "label": "Feuchtigkeit",
    "type": "multi",
    "options": [
     "sehr trocken",
     "trocken",
     "frisch",
     "feucht",
     "nass",
     "sumpfig"
    ]
   },
   {
    "key": "plant_site.nutrient_demand",
    "table": "plant_site",
    "col": "nutrient_demand",
    "label": "Nährstoffbedarf",
    "type": "multi",
    "options": [
     "sehr gering",
     "gering",
     "mittel",
     "hoch",
     "sehr hoch"
    ]
   },
   {
    "key": "plant_site.soil_type",
    "table": "plant_site",
    "col": "soil_type",
    "label": "Bodenart",
    "type": "multi",
    "options": [
     "steinig",
     "sandig",
     "schluffig",
     "tonig",
     "lehmig",
     "moorig",
     "humos"
    ]
   },
   {
    "key": "plant_site.soil_ph_min",
    "table": "plant_site",
    "col": "soil_ph_min",
    "label": "pH-Minimum",
    "type": "number"
   },
   {
    "key": "plant_site.soil_ph_max",
    "table": "plant_site",
    "col": "soil_ph_max",
    "label": "pH-Maximum",
    "type": "number"
   }
  ]
 },
 {
  "key": "flower",
  "title": "Blüte",
  "fields": [
   {
    "key": "plant_flower.color",
    "table": "plant_flower",
    "col": "color",
    "label": "Blütenfarbe",
    "type": "multi",
    "options": [
     "weiss",
     "cremeweiss",
     "hellgelb",
     "gelb",
     "goldgelb",
     "orange",
     "apricot",
     "hellrot",
     "rot",
     "dunkelrot",
     "hellrosa",
     "rosa",
     "pink",
     "dunkelrosa",
     "hellviolett",
     "flieder",
     "violett",
     "purpur",
     "dunkelpurpur",
     "dunkelblau",
     "blau",
     "himmelblau",
     "hellblau",
     "türkis",
     "dunkelgrün",
     "olivgrün",
     "grün",
     "hellgrün",
     "braun"
    ]
   },
   {
    "key": "plant_flower.fragrance",
    "table": "plant_flower",
    "col": "fragrance",
    "label": "Duft",
    "type": "select",
    "options": []
   },
   {
    "key": "plant_flower.blooming_cycle",
    "table": "plant_flower",
    "col": "blooming_cycle",
    "label": "Blühzyklus",
    "type": "select",
    "options": []
   },
   {
    "key": "plant_flower.size",
    "table": "plant_flower",
    "col": "size",
    "label": "Blütengrösse",
    "type": "text"
   },
   {
    "key": "plant_flower.fullness",
    "table": "plant_flower",
    "col": "fullness",
    "label": "Füllung",
    "type": "select",
    "options": []
   },
   {
    "key": "plant_flower.form",
    "table": "plant_flower",
    "col": "form",
    "label": "Blütenform",
    "type": "text"
   },
   {
    "key": "plant_flower.vase_life",
    "table": "plant_flower",
    "col": "vase_life",
    "label": "Vasenleben",
    "type": "text"
   },
   {
    "key": "plant_flower.notes",
    "table": "plant_flower",
    "col": "notes",
    "label": "Notizen Blüte",
    "type": "text"
   },
   {
    "key": "plant_flower.bloom_months",
    "table": "plant_flower",
    "col": "bloom_months",
    "label": "Blühmonate",
    "type": "multi",
    "options": [
     "Januar",
     "Februar",
     "Maerz",
     "April",
     "Mai",
     "Juni",
     "Juli",
     "August",
     "September",
     "Oktober",
     "November",
     "Dezember"
    ]
   }
  ]
 },
 {
  "key": "growth",
  "title": "Wuchs",
  "fields": [
   {
    "key": "plant_growth.growth_cycle",
    "table": "plant_growth",
    "col": "growth_cycle",
    "label": "Lebenszyklus",
    "type": "select",
    "options": []
   },
   {
    "key": "plant_growth.height_cm",
    "table": "plant_growth",
    "col": "height_cm",
    "label": "Höhe (cm)",
    "type": "number"
   },
   {
    "key": "plant_growth.width_cm",
    "table": "plant_growth",
    "col": "width_cm",
    "label": "Breite (cm)",
    "type": "number"
   },
   {
    "key": "plant_growth.growth_form_primary",
    "table": "plant_growth",
    "col": "growth_form_primary",
    "label": "Wuchsform (primär)",
    "type": "multi",
    "options": [
     "aufrecht",
     "bogig",
     "buschig",
     "horstig",
     "kletternd",
     "kriechend",
     "auslaeuferbildend",
     "saeulenfoermig",
     "kugelfoermig",
     "haengend",
     "spreizend",
     "polsterfoermig",
     "rosettenfoermig"
    ]
   },
   {
    "key": "plant_growth.growth_form_secondary",
    "table": "plant_growth",
    "col": "growth_form_secondary",
    "label": "Wuchsform (sekundär)",
    "type": "multi",
    "options": [
     "kompakt",
     "locker",
     "dicht",
     "weitausladend",
     "uebershaengend",
     "sparrig"
    ]
   },
   {
    "key": "plant_growth.growth_vigor",
    "table": "plant_growth",
    "col": "growth_vigor",
    "label": "Wüchsigkeit",
    "type": "select",
    "options": []
   },
   {
    "key": "plant_growth.notes",
    "table": "plant_growth",
    "col": "notes",
    "label": "Notizen Wuchs",
    "type": "text"
   }
  ]
 },
 {
  "key": "origin",
  "title": "Herkunft & Züchtung",
  "fields": [
   {
    "key": "plant_origin.breeder_name",
    "table": "plant_origin",
    "col": "breeder_name",
    "label": "Züchter",
    "type": "text"
   },
   {
    "key": "plant_origin.breeder_company",
    "table": "plant_origin",
    "col": "breeder_company",
    "label": "Firma",
    "type": "text"
   },
   {
    "key": "plant_origin.breeder_country",
    "table": "plant_origin",
    "col": "breeder_country",
    "label": "Land",
    "type": "text"
   },
   {
    "key": "plant_origin.breeder_year",
    "table": "plant_origin",
    "col": "breeder_year",
    "label": "Züchtungsjahr",
    "type": "number"
   },
   {
    "key": "plant_origin.introducer_name",
    "table": "plant_origin",
    "col": "introducer_name",
    "label": "Einführer",
    "type": "text"
   },
   {
    "key": "plant_origin.introducer_country",
    "table": "plant_origin",
    "col": "introducer_country",
    "label": "Einführerland",
    "type": "text"
   },
   {
    "key": "plant_origin.introducer_year",
    "table": "plant_origin",
    "col": "introducer_year",
    "label": "Einführungsjahr",
    "type": "number"
   },
   {
    "key": "plant_origin.hybrid_status",
    "table": "plant_origin",
    "col": "hybrid_status",
    "label": "Hybrid-Status",
    "type": "select",
    "options": []
   },
   {
    "key": "plant_origin.ploidy",
    "table": "plant_origin",
    "col": "ploidy",
    "label": "Ploidie",
    "type": "select",
    "options": []
   },
   {
    "key": "plant_origin.hybrid_parents",
    "table": "plant_origin",
    "col": "hybrid_parents",
    "label": "Elternpflanzen",
    "type": "text"
   },
   {
    "key": "plant_origin.breeder_reference",
    "table": "plant_origin",
    "col": "breeder_reference",
    "label": "Züchterreferenz",
    "type": "text"
   },
   {
    "key": "plant_origin.origin_region",
    "table": "plant_origin",
    "col": "origin_region",
    "label": "Herkunftsregion",
    "type": "text"
   },
   {
    "key": "plant_origin.breeding_method",
    "table": "plant_origin",
    "col": "breeding_method",
    "label": "Züchtungsmethode",
    "type": "text"
   },
   {
    "key": "plant_origin.variety_protection",
    "table": "plant_origin",
    "col": "variety_protection",
    "label": "Sortenschutz",
    "type": "checkbox"
   },
   {
    "key": "plant_origin.trademark_protection",
    "table": "plant_origin",
    "col": "trademark_protection",
    "label": "Markenschutz",
    "type": "checkbox"
   }
  ]
 },
 {
  "key": "fruit",
  "title": "Frucht",
  "fields": [
   {
    "key": "plant_fruit.color",
    "table": "plant_fruit",
    "col": "color",
    "label": "Fruchtfarbe",
    "type": "text"
   },
   {
    "key": "plant_fruit.size",
    "table": "plant_fruit",
    "col": "size",
    "label": "Fruchtgrösse",
    "type": "text"
   },
   {
    "key": "plant_fruit.taste",
    "table": "plant_fruit",
    "col": "taste",
    "label": "Geschmack",
    "type": "text"
   },
   {
    "key": "plant_fruit.juiciness",
    "table": "plant_fruit",
    "col": "juiciness",
    "label": "Saftigkeit",
    "type": "select",
    "options": []
   },
   {
    "key": "plant_fruit.texture",
    "table": "plant_fruit",
    "col": "texture",
    "label": "Textur",
    "type": "text"
   },
   {
    "key": "plant_fruit.development_days",
    "table": "plant_fruit",
    "col": "development_days",
    "label": "Entwicklungstage",
    "type": "number"
   },
   {
    "key": "plant_fruit.harvest_months",
    "table": "plant_fruit",
    "col": "harvest_months",
    "label": "Erntemonate",
    "type": "multi",
    "options": [
     "Januar",
     "Februar",
     "Maerz",
     "April",
     "Mai",
     "Juni",
     "Juli",
     "August",
     "September",
     "Oktober",
     "November",
     "Dezember"
    ]
   },
   {
    "key": "plant_fruit.notes",
    "table": "plant_fruit",
    "col": "notes",
    "label": "Notizen Frucht",
    "type": "text"
   }
  ]
 },
 {
  "key": "leaf",
  "title": "Blatt",
  "fields": [
   {
    "key": "plant_leaf.color",
    "table": "plant_leaf",
    "col": "color",
    "label": "Blattfarbe",
    "type": "text"
   },
   {
    "key": "plant_leaf.form",
    "table": "plant_leaf",
    "col": "form",
    "label": "Blattform",
    "type": "text"
   },
   {
    "key": "plant_leaf.size",
    "table": "plant_leaf",
    "col": "size",
    "label": "Blattgrösse",
    "type": "text"
   },
   {
    "key": "plant_leaf.aroma",
    "table": "plant_leaf",
    "col": "aroma",
    "label": "Blattaroma",
    "type": "text"
   },
   {
    "key": "plant_leaf.autumn_color",
    "table": "plant_leaf",
    "col": "autumn_color",
    "label": "Herbstfarbe",
    "type": "text"
   },
   {
    "key": "plant_leaf.has_autumn_color",
    "table": "plant_leaf",
    "col": "has_autumn_color",
    "label": "Herbstfärbung",
    "type": "checkbox"
   },
   {
    "key": "plant_leaf.evergreen",
    "table": "plant_leaf",
    "col": "evergreen",
    "label": "Immergrün",
    "type": "checkbox"
   },
   {
    "key": "plant_leaf.notes",
    "table": "plant_leaf",
    "col": "notes",
    "label": "Notizen Blatt",
    "type": "text"
   }
  ]
 },
 {
  "key": "root",
  "title": "Wurzel",
  "fields": [
   {
    "key": "plant_root.root_depth",
    "table": "plant_root",
    "col": "root_depth",
    "label": "Wurzeltiefe",
    "type": "text"
   },
   {
    "key": "plant_root.root_form",
    "table": "plant_root",
    "col": "root_form",
    "label": "Wurzelform",
    "type": "text"
   },
   {
    "key": "plant_root.anchorage",
    "table": "plant_root",
    "col": "anchorage",
    "label": "Verankerung",
    "type": "text"
   },
   {
    "key": "plant_root.taste",
    "table": "plant_root",
    "col": "taste",
    "label": "Geschmack",
    "type": "text"
   },
   {
    "key": "plant_root.notes",
    "table": "plant_root",
    "col": "notes",
    "label": "Notizen Wurzel",
    "type": "text"
   }
  ]
 },
 {
  "key": "usage",
  "title": "Verwendung",
  "fields": [
   {
    "key": "plant_usage.purpose",
    "table": "plant_usage",
    "col": "purpose",
    "label": "Verwendungszweck",
    "type": "text"
   },
   {
    "key": "plant_usage.plant_parts",
    "table": "plant_usage",
    "col": "plant_parts",
    "label": "Pflanzenteile",
    "type": "text"
   },
   {
    "key": "plant_usage.processing",
    "table": "plant_usage",
    "col": "processing",
    "label": "Verarbeitung",
    "type": "text"
   },
   {
    "key": "plant_usage.storage",
    "table": "plant_usage",
    "col": "storage",
    "label": "Lagerung",
    "type": "text"
   },
   {
    "key": "plant_usage.notes",
    "table": "plant_usage",
    "col": "notes",
    "label": "Notizen Verwendung",
    "type": "text"
   },
   {
    "key": "plant_usage.harvest_months",
    "table": "plant_usage",
    "col": "harvest_months",
    "label": "Erntemonate",
    "type": "multi",
    "options": [
     "Januar",
     "Februar",
     "Maerz",
     "April",
     "Mai",
     "Juni",
     "Juli",
     "August",
     "September",
     "Oktober",
     "November",
     "Dezember"
    ]
   }
  ]
 },
 {
  "key": "nursery",
  "title": "Gärtnerei & Kultur",
  "fields": [
   {
    "key": "plant_nursery.propagation_method",
    "table": "plant_nursery",
    "col": "propagation_method",
    "label": "Vermehrungsart",
    "type": "text"
   },
   {
    "key": "plant_nursery.germination_days",
    "table": "plant_nursery",
    "col": "germination_days",
    "label": "Keimdauer (Tage)",
    "type": "number"
   },
   {
    "key": "plant_nursery.germination_temp_min_c",
    "table": "plant_nursery",
    "col": "germination_temp_min_c",
    "label": "Keimtemp. min (°C)",
    "type": "number"
   },
   {
    "key": "plant_nursery.germination_temp_max_c",
    "table": "plant_nursery",
    "col": "germination_temp_max_c",
    "label": "Keimtemp. max (°C)",
    "type": "number"
   },
   {
    "key": "plant_nursery.germination_light",
    "table": "plant_nursery",
    "col": "germination_light",
    "label": "Keimlicht",
    "type": "text"
   },
   {
    "key": "plant_nursery.rooting_days",
    "table": "plant_nursery",
    "col": "rooting_days",
    "label": "Bewurzelungstage",
    "type": "number"
   },
   {
    "key": "plant_nursery.development_days_to_yp",
    "table": "plant_nursery",
    "col": "development_days_to_yp",
    "label": "Tage bis Jungpflanze",
    "type": "number"
   },
   {
    "key": "plant_nursery.development_days_yp_to_fp",
    "table": "plant_nursery",
    "col": "development_days_yp_to_fp",
    "label": "Tage bis Fertigpflanze",
    "type": "number"
   },
   {
    "key": "plant_nursery.plants_per_sqm",
    "table": "plant_nursery",
    "col": "plants_per_sqm",
    "label": "Pflanzen pro m²",
    "type": "number"
   },
   {
    "key": "plant_nursery.notes",
    "table": "plant_nursery",
    "col": "notes",
    "label": "Notizen Kultur",
    "type": "text"
   }
  ]
 }
]
JSON
, true);
    }
    return $c;
}
