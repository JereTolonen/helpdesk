# Linux-palvelimet

## Palvelu ei käynnisty (systemd)
1. Tarkista tila: `sudo systemctl status palvelu` (esim. nginx, apache2, mysql).
2. Lue lokit: `sudo journalctl -u palvelu -n 50 --no-pager`.
3. Tarkista asetustiedoston syntaksi ennen uudelleenkäynnistystä: `sudo nginx -t` tai `sudo apachectl configtest`.
4. Käynnistä uudelleen: `sudo systemctl restart palvelu` ja ota käyttöön käynnistyksessä `sudo systemctl enable palvelu`.

## Levy täynnä
Tarkista tila komennolla `df -h` ja etsi suuret kansiot `sudo du -xh / --max-depth=1 | sort -h`. Yleisimmät syyt ovat kasvaneet lokit (/var/log), vanhat ytimet ja Docker-levykuvat (`docker system prune`). Lokit voi siivota turvallisesti: `sudo journalctl --vacuum-time=7d`.

## SSH-yhteys ei toimi
- "Connection refused": sshd ei ole käynnissä tai portti on väärä (`sudo systemctl status ssh`).
- "Connection timed out": palomuuri tai verkko estää (tarkista `sudo ufw status`).
- "Permission denied (publickey)": julkinen avain puuttuu tiedostosta ~/.ssh/authorized_keys tai oikeudet ovat väärät (`chmod 700 ~/.ssh` ja `chmod 600 ~/.ssh/authorized_keys`).
- Yhteyttä voi debugata komennolla `ssh -v kayttaja@palvelin`.

## Portti on jo käytössä
Virhe "Address already in use": selvitä mikä prosessi käyttää porttia `sudo ss -tulpn | grep :80` ja pysäytä se tai vaihda palvelun porttia.

## Oikeusongelmat
"Permission denied" tiedostoja käsiteltäessä: tarkista omistaja ja oikeudet `ls -l`. Web-palvelimen tiedostojen omistaja on yleensä www-data: `sudo chown -R www-data:www-data /var/www/sivusto`. Älä käytä `chmod 777`.
