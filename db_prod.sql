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

-- ----------------------------
-- Table structure for prods
-- ----------------------------
CREATE TABLE IF NOT EXISTS prods (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "title" TEXT NOT NULL,
  "description" TEXT NOT NULL,
  "ean" TEXT NOT NULL,
  "stock" INTEGER NOT NULL,
  "price" real NOT NULL,
  "time_updated" TEXT NOT NULL,
  "bol_id" integer,
  "bol_updated" text,
  "prod_id" INTEGER,
  "cats" TEXT NOT NULL
);

-- ----------------------------
-- Indexes structure for table prods
-- ----------------------------
CREATE UNIQUE INDEX IF NOT EXISTS "unique_ean"
ON "prods" (
  "ean" ASC,
  "title"
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

PRAGMA foreign_keys = true;

