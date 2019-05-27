/*
 Navicat Premium Data Transfer

 Source Server         : dropship
 Source Server Type    : SQLite
 Source Server Version : 3026000
 Source Schema         : main

 Target Server Type    : SQLite
 Target Server Version : 3026000
 File Encoding         : 65001

 Date: 06/04/2019 17:10:59
*/

PRAGMA foreign_keys = false;

CREATE TABLE IF NOT EXISTS brands (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "discount" INTEGER NOT NULL
);

-- ----------------------------
-- Table structure for prods
-- ----------------------------
CREATE TABLE IF NOT EXISTS prods (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "title" TEXT NOT NULL,
  "description" TEXT NOT NULL,
  "ean" TEXT NOT NULL,
  "stock" INTEGER,
  "price" real NOT NULL,
  "price_me" real NOT NULL,
  "vat" integer not null,
  "brand_id" integer not null,
  "discount" integer not null,
  "time_updated" TEXT NOT NULL,
  "edc_artnum" TEXT NOT NULL,
  "bol_id" text,
  "bol_updated" text,
  "bol_error" INTEGER,
  "prod_id" INTEGER,
  "cats" TEXT NOT NULL,
  "bol_stock" INTEGER,
  "bol_pending" INTEGER,
  "bol_price" real,
  "calc_price_bol" real not null,
  "calc_price_site" real not null
);

CREATE TABLE IF NOT EXISTS bol_prods (
   "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
   "name" TEXT NOT NULL,
   "chunkid" TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS "unique_bol_prod"
ON "bol_prods" (
  "chunkid"
);

CREATE TABLE IF NOT EXISTS bol_prod_attrs (
   "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
   "bol_prod_id" INTEGER NOT NULL,
   "name" TEXT NOT NULL,
   "label" TEXT NOT NULL,
   "definition" TEXT
);

-- ----------------------------
-- Indexes structure for table prods
-- ----------------------------
CREATE UNIQUE INDEX IF NOT EXISTS "unique_ean"
ON "prods" (
  "ean" ASC,
  "id"
);

CREATE UNIQUE INDEX IF NOT EXISTS "unique_bol"
ON "prods" (
  "bol_id" ASC
);

CREATE TABLE IF NOT EXISTS cats (
  "id" INTEGER NOT NULL,
  "title" TEXT NOT NULL,
  PRIMARY KEY ("id")
);

CREATE UNIQUE INDEX IF NOT EXISTS "unique_cat"
ON "cats" (
  "title"
);

CREATE TABLE IF NOT EXISTS bol_del (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "bol_id" text,
  "tm_added" TEXT NOT NULL,
  "tm_synced" TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS "unique_boldel"
ON "bol_del" (
  "bol_id"
);

CREATE TABLE IF NOT EXISTS prod_img (
  "ean" TEXT NOT NULL PRIMARY KEY,
  "count" INTEGER NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS "unique_prodimg"
ON "prod_img" (
  "ean"
);

PRAGMA foreign_keys = true;

