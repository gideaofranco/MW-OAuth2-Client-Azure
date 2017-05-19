--
-- https://www.mediawiki.org/wiki/Manual:SQL_patch_file
-- extension Oauth2 client state SQL schema
--
BEGIN;

CREATE TABLE /*_*/`oauth2_client_states` (
  `state` VARCHAR(255) NOT NULL,
  `return_to` VARCHAR(45) NULL,
  PRIMARY KEY (`state`),
  UNIQUE INDEX `state_UNIQUE` (`state` ASC)
);

COMMIT;
