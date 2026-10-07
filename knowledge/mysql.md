# MySQL ja MariaDB

## Palvelin ei käynnisty
1. Tarkista tila ja virheet: Linux `sudo systemctl status mysql` (tai mariadb) ja `sudo journalctl -u mysql -n 50`; lisäksi virheloki /var/log/mysql/error.log.
2. Windows/XAMPP: avaa XAMPP Control Panel → MySQL → Logs. Yleisin syy on, että portti 3306 on jo käytössä (toinen MySQL-asennus tai palvelu). Tarkista `netstat -ano | findstr 3306` ja pysäytä toinen palvelu tai vaihda portti my.ini-tiedostossa.
3. Levytila: täysi levy estää käynnistymisen (`df -h`).
4. XAMPP:ssa "MySQL shutdown unexpectedly" johtuu usein vioittuneista tiedostoista data-kansiossa. Ota varmuuskopio ennen korjausyrityksiä, äläkä poista ibdata1-tiedostoa ilman ohjeita.

## ERROR 1045 (28000): Access denied for user
Väärä käyttäjätunnus tai salasana, tai käyttäjällä ei ole oikeutta kirjautua kyseisestä osoitteesta (esim. 'kayttaja'@'localhost' vs '%'). Tarkista: `SELECT user, host FROM mysql.user;` ja anna oikeudet: `GRANT ALL PRIVILEGES ON kanta.* TO 'kayttaja'@'localhost';`.

## ERROR 2002: Can't connect to local MySQL server through socket
Palvelin ei ole käynnissä tai socket-polku on väärä. Käynnistä palvelin. PHP:ssa kokeile hostiksi 127.0.0.1 eikä localhost (pakottaa TCP-yhteyden). Dockerissa host on tietokantapalvelun nimi, esim. `db`.

## ERROR 1064: You have an error in your SQL syntax
Lue virheilmoituksen "near ..." -kohta: virhe on yleensä juuri ennen sitä. Yleisiä syitä: puuttuva pilkku tai lainausmerkki, varattu sana sarakkeen nimenä (käytä `takahipsuja`) tai MySQL-versioon sopimaton syntaksi.

## ERROR 1452: Cannot add or update a child row (foreign key)
Viiteavaimen arvoa ei löydy viitatusta taulusta. Lisää ensin rivi päätauluun tai korjaa arvo. Tuonnissa taulujen järjestys on tärkeä.

## Merkistöongelmat (ä ja ö näkyvät väärin)
Käytä merkistönä utf8mb4 sekä tauluissa että yhteydessä: PDO DSN `charset=utf8mb4` ja SQL-tiedoston alkuun `SET NAMES utf8mb4;`.
