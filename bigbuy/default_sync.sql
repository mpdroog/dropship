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
CREATE TABLE IF NOT EXISTS orders (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "bol_id" INTEGER NOT NULL,
  "edc_id" INTEGER,
  "tnt_track" TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS "unique_bol"
ON "orders" (
  "bol_id"
);
CREATE UNIQUE INDEX IF NOT EXISTS "unique_edc"
ON "orders" (
  "edc_id"
);

PRAGMA foreign_keys = true;
