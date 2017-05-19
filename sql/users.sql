--
-- https://www.mediawiki.org/wiki/Manual:SQL_patch_file
-- extension Oauth2 client users SQL schema
--
BEGIN;

CREATE TABLE /*_*/`oauth2_client_users` (
  `external_id` VARCHAR(255) NOT NULL,
  `internal_id` INT(10) NOT NULL,
  PRIMARY KEY (`external_id`),
  UNIQUE INDEX `state_UNIQUE` (`external_id` ASC),
  UNIQUE INDEX `internal_id_UNIQUE` (`internal_id` ASC)
);

COMMIT;
