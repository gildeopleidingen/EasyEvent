ALTER TABLE gebruiker
ADD rol varchar(20);

DELETE FROM gebruiker_rol  WHERE rol_id = 1 AND gebruiker_id = 10;

UPDATE gebruiker g
JOIN gebruiker_rol gr ON g.id = gr.gebruiker_id
JOIN rol r ON gr.rol_id = r.id
SET g.rol = r.rol;

ALTER TABLE rol ADD UNIQUE (rol);

ALTER TABLE gebruiker
ADD CONSTRAINT fk_rollen
FOREIGN KEY (rol)
REFERENCES rol(rol)
ON DELETE SET NULL
ON UPDATE CASCADE;

Drop TABLE gebruiker_rol;

CREATE TABLE kinderen(
  id int PRIMARY KEY AUTO_INCREMENT,
  voornaam varchar(20),
  achternaam varchar(30),
  geboortedatum date,
  postcode varchar(7),
  Plaatsnaam varchar(40),
  huisnummer int,
  ouder_id int
);

ALTER TABLE kinderen
ADD CONSTRAINT fk_kinderen_ouders
FOREIGN KEY (ouder_id)
REFERENCES gebruiker(id)
ON DELETE SET NULL
ON UPDATE CASCADE;

ALTER TABLE gebruiker
DROP FOREIGN KEY fk_gebruiker_ouder;

ALTER TABLE gebruiker 
DROP FOREIGN KEY fk_gebruiker_kledingmaat;

ALTER TABLE gebruiker
DROP COLUMN ouder_id;

ALTER TABLE gebruiker
  MODIFY voornaam    VARCHAR(25)  NOT NULL,
  MODIFY achternaam  VARCHAR(25)  NOT NULL,
  MODIFY email       VARCHAR(100) NOT NULL,
  MODIFY wachtwoord  VARCHAR(100)  NOT NULL AFTER email,
  MODIFY telefoon    VARCHAR(12)   AFTER wachtwoord,
  MODIFY postcode    VARCHAR(7)   AFTER telefoon,
  MODIFY plaatsnaam  VARCHAR(40)  AFTER postcode,
  MODIFY huisnummer  VARCHAR(5)   AFTER plaatsnaam,
CHANGE profiel_foto profielfoto BLOB AFTER Kleding_maat_id;

TRUNCATE TABLE kleding_maat;

INSERT INTO kleding_maat(id, maat)
VALUES
('1', 'N/A'),
('2', 'XXS'),
('3', 'XS'),
('4', 'S'),
('5', 'M'),
('6', 'L'),
('7', 'XL'),
('8', 'XXL');

ALTER TABLE kleding_maat ADD UNIQUE KEY uq_kleding_maat_maat (maat);

ALTER TABLE gebruiker ADD CONSTRAINT fk_gebruiker_kledingmaat FOREIGN KEY (Kleding_maat_id) REFERENCES kleding_maat (id) ON UPDATE CASCADE ON DELETE SET NULL;

