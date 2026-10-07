# Ohjelmointi – yleiset ongelmat

## Virheen selvittäminen
1. Lue virheilmoitus kokonaan: tiedosto, rivinumero ja virhetyyppi kertovat yleensä tarkalleen, missä vika on.
2. Toista ongelma pienimmällä mahdollisella esimerkillä.
3. Lisää tulosteita tai käytä debuggeria (VS Code: F5) muuttujien arvojen tarkistamiseen.
4. Hae virheilmoituksella – poista ensin omat tiedostopolut ja nimet.

## PHP
- Valkoinen sivu: virheiden näyttö on pois päältä. Kehityksessä lisää alkuun `ini_set('display_errors', '1'); error_reporting(E_ALL);` tai lue palvelimen error.log.
- "Undefined index/array key": tarkista avaimen olemassaolo `isset()` tai käytä `??`-operaattoria.
- "Headers already sent": tiedostossa on tulostetta (esim. välilyönti ennen `<?php`) ennen `header()`-kutsua.
- SQL-injektion estämiseksi käytä aina PDO:n valmisteltuja lauseita (`prepare` + `execute`).

## JavaScript
- Avaa selaimen kehittäjätyökalut (F12) → Console nähdäksesi virheet.
- "Cannot read properties of undefined/null": elementtiä tai dataa ei ole olemassa siinä vaiheessa – tarkista valitsin tai odota `DOMContentLoaded`.
- CORS-virhe: palvelin ei salli pyyntöä toisesta osoitteesta; asetus tehdään palvelimen puolella.

## Python
- "ModuleNotFoundError": asenna paketti oikeaan ympäristöön (`python -m pip install paketti`) ja tarkista, että virtuaaliympäristö on aktivoitu.
- "IndentationError": sekoitettu välilyönti- ja sarkainsisennys. Käytä neljää välilyöntiä.

## Node.js / npm
- "Cannot find module": aja `npm install`. Ongelmatilanteissa poista node_modules ja package-lock.json ja asenna uudelleen.
- Portti käytössä (EADDRINUSE): toinen prosessi käyttää samaa porttia – sulje se tai vaihda porttia.
