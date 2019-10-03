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
