/**
 * Laat het kader van de ticketshop meegroeien met wat erin staat.
 *
 * De winkel stuurt bij elke wijziging een bericht met zijn hoogte:
 *   { voetbalplannerShopHoogte: 1234 }
 *
 * Wij nemen dat alleen aan van een venster dat ook echt in een van onze
 * iframes zit; een willekeurige andere pagina mag de hoogte niet zetten.
 */
(function () {
    'use strict';

    function kaders() {
        return Array.prototype.slice.call(
            document.querySelectorAll('iframe.vp-ticketshop-kader')
        );
    }

    /** Het iframe waar dit bericht vandaan komt, of null. */
    function afzender(bron) {
        var gevonden = null;

        kaders().forEach(function (kader) {
            if (kader.contentWindow === bron) {
                gevonden = kader;
            }
        });

        return gevonden;
    }

    /** Komt het bericht van hetzelfde adres als waar het kader naar wijst? */
    function zelfdeHerkomst(kader, herkomst) {
        var anker = document.createElement('a');
        anker.href = kader.src;

        return anker.origin === herkomst;
    }

    window.addEventListener('message', function (bericht) {
        var gegevens = bericht.data;

        if (!gegevens || typeof gegevens !== 'object') {
            return;
        }

        var hoogte = parseInt(gegevens.voetbalplannerShopHoogte, 10);

        if (!hoogte || hoogte < 100 || hoogte > 20000) {
            return;
        }

        var kader = afzender(bericht.source);

        if (!kader || !zelfdeHerkomst(kader, bericht.origin)) {
            return;
        }

        // Een paar pixels erbij, anders houdt een afgeronde hoogte toch nog
        // een scrollbalk over.
        kader.style.height = (hoogte + 4) + 'px';
        kader.style.minHeight = '0';
    });
})();
