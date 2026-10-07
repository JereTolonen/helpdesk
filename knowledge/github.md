# Git ja GitHub

## Push hylätään: "Authentication failed" / "Support for password authentication was removed"
GitHub ei hyväksy tilin salasanaa komentorivillä. Käytä Personal Access Tokenia (GitHub → Settings → Developer settings → Personal access tokens) salasanan sijaan, kirjaudu GitHub CLI:llä (`gh auth login`) tai käytä SSH-avainta.

## SSH-avaimen käyttöönotto
1. Luo avain: `ssh-keygen -t ed25519 -C "oma@email.fi"`.
2. Kopioi julkinen avain (~/.ssh/id_ed25519.pub) GitHubiin: Settings → SSH and GPG keys → New SSH key.
3. Testaa: `ssh -T git@github.com`.
4. Vaihda repositorion osoite: `git remote set-url origin git@github.com:kayttaja/repo.git`.

## Push hylätään: "Updates were rejected because the remote contains work"
Etärepositoriossa on muutoksia, joita sinulla ei ole. Hae ne ensin: `git pull --rebase origin main`, ratkaise mahdolliset konfliktit ja tee sitten `git push`. Älä käytä `git push --force` yhteisessä haarassa.

## Merge-konflikti
Avaa konfliktitiedostot, etsi merkinnät `<<<<<<<`, `=======` ja `>>>>>>>`, valitse oikea sisältö ja poista merkit. Sen jälkeen `git add tiedosto` ja `git commit` (tai `git rebase --continue`).

## Branchit ja Pull Requestit
Uusi haara: `git switch -c feature/ominaisuus`. Työnnä: `git push -u origin feature/ominaisuus` ja avaa Pull Request GitHubissa. Pidä commitit pieninä ja viestit kuvaavina.

## Vahingossa commitoitu salasana tai avain
Vaihda vuotanut salasana tai avain HETI – historian siivous ei riitä, koska tieto on jo voinut kopioitua. Poista tieto historiasta esim. `git filter-repo`-työkalulla ja lisää tiedosto .gitignoreen.
