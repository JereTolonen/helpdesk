# Verkko ja VPN

## Wi-Fi ei yhdistä
1. Varmista, että käytät oikeaa verkkoa (esim. koulun eduroam tai opiskelijaverkko, ei vierasverkko).
2. Unohda verkko ja yhdistä uudelleen: Asetukset → Verkko → Wi-Fi → Hallitse tunnettuja verkkoja → Unohda.
3. eduroam: käyttäjätunnus on muotoa tunnus@oppilaitos.fi ja salasana on oppilaitoksen verkkotunnuksen salasana.
4. Käynnistä laite uudelleen ja kokeile toista laitetta. Jos mikään laite ei yhdistä, kyseessä voi olla verkkokatko – ilmoita tukeen.

## Yhteys on mutta internet ei toimi
- Windows: avaa komentokehote ja aja `ipconfig /release`, `ipconfig /renew` ja `ipconfig /flushdns`.
- Jos IP-osoite alkaa 169.254, laite ei saa osoitetta DHCP:ltä – yhdistä verkkoon uudelleen.
- Kokeile avata https://1.1.1.1 – jos se aukeaa mutta nimet eivät, ongelma on DNS:ssä.

## VPN ei yhdistä
1. Tarkista, että internet-yhteys toimii ilman VPN:ää.
2. Varmista, että VPN-ohjelma on päivitetty uusimpaan versioon.
3. Tarkista tunnus ja salasana sekä MFA-vahvistus puhelimessa.
4. Kokeile toista verkkoa (esim. puhelimen jakamaa yhteyttä) – jotkin julkiset verkot estävät VPN-liikenteen.
5. Kirjaa ylös tarkka virheilmoitus ja virhekoodi, jos ongelma jatkuu.
