CREATE TABLE IF NOT EXISTS prod_variants (
  "id" INTEGER NOT NULL PRIMARY KEY,
  "product_id" INTEGER NOT NULL,
  "ean" TEXT NOT NULL,
  "sku" text not null,
  "wholesalePrice" real not null,
  "retailPrice" real not null,

  "stock" integer,
  "stock_days" integer
);
CREATE UNIQUE INDEX IF NOT EXISTS "unique_sku_variant"
ON "prod_variants" (
  "sku"
);

CREATE TABLE IF NOT EXISTS prods (
  "id" INTEGER NOT NULL PRIMARY KEY,
  "name" TEXT,
  "manufacturer" INTEGER,
  "ean" TEXT NOT NULL,
  "sku" text not null,
  "dateUpd" INTEGER NOT NULL,
  "category" INTEGER NOT NULL,
  "dateUpdDescription" INTEGER NOT NULL,
  "dateUpdStock" INTEGER NOT NULL,
  "wholesalePrice" real not null,
  "retailPrice" real not null,
  "dateAdd" INTEGER NOT NULL,
  "taxRate" INTEGER NOT NULL,
  "dateUpdProperties" INTEGER,
  "dateUpdCategories" INTEGER,
  "inShopsPrice" REAL NOT NULL,
  "stock" integer,
  "stock_days" integer,
  "description" TEXT,

  "weight" real,
  "height" real,
  "width" real,
  "depth" real,

  "bol_id" text,
  "bol_updated" text,
  "bol_error" INTEGER,
  "bol_stock" INTEGER,
  "bol_pending" INTEGER,
  "bol_price" real,
  "calc_price_bol" real
);

CREATE UNIQUE INDEX IF NOT EXISTS "unique_sku"
ON "prods" (
  "sku"
);
/*CREATE UNIQUE INDEX IF NOT EXISTS "unique_ean"
ON "prods" (
  "ean"
);*/

CREATE TABLE IF NOT EXISTS cats (
  "id" INTEGER NOT NULL PRIMARY KEY,
  "name" TEXT NOT NULL,
  "parentCategory" INTEGER,
  "dateUpd" INTEGER NOT NULL,
  "dateAdd" INTEGER NOT NULL,
  "isoCode" TEXT NOT NULL
);

/*CREATE UNIQUE INDEX IF NOT EXISTS "unique_cat"
ON "cats" (
  "name"
);*/

CREATE TABLE IF NOT EXISTS "shipping" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "weight_modulo" real NOT NULL,
  "weight_max" real NOT NULL,
  "delay" TEXT NOT NULL,
  "cost" real NOT NULL,
  "shipping_service_id" INTEGER NOT NULL,
  "shipping_name" TEXT NOT NULL,
  "shipping_method" TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS "unique_shipping"
ON "shipping" (
  "shipping_service_id" asc,
  "weight_modulo" asc
);

CREATE TABLE IF NOT EXISTS "shipestimate" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "weight_modulo" real NOT NULL,
  "delay" TEXT NOT NULL,
  "cost" real NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS "unique_ship_est"
ON "shipestimate" (
  "weight_modulo" asc
);
