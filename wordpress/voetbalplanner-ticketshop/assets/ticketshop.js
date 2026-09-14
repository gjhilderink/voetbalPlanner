/**
 * Het kader van de ticketshop: meegroeien, en de betaling doorlaten.
 *
 * De winkel stuurt twee soorten berichten:
 *   { voetbalplannerShopHoogte: 1234 }        - zet het kader op die hoogte
 *   { voetbalplannerShopBetaling: "https://" } - ga naar de betaalpagina
 *
 * Wij nemen die alleen aan van een venster dat ook echt in een van onze
 * iframes zit én van hetzelfde adres als waar dat kader naar wijst; een
 * willekeurige andere pagina mag het kader niet oprekken en al helemaal niet
 * deze pagina ergens anders naartoe sturen.
 */
(function () {
    'use strict';

    /**
     * Kleinere verschillen dan dit negeren we.
     *
     * Het iframe hoger zetten maakt het venster ín het iframe hoger, en een
     * winkel die de hoogte van dát venster terugmeldt vraagt daarmee om de
     * hoogte die er net is gezet. Komt daar ook maar één pixel bij - een
     * afronding, een rand - dan is er een lus die zichzelf opvoert, en staat
     * een kader van 900 pixels binnen een seconde op twintigduizend. Een marge
     * breekt die lus: een echte wijziging is altijd groter dan een paar pixels.
     */
    var TOLERANTIE = 8;

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

    /** Het kader op de gemelde hoogte zetten. */
    function groei(kader, hoogte) {
        hoogte = parseInt(hoogte, 10);

        if (!hoogte || hoogte < 100 || hoogte > 20000) {
            return;
        }

        var huidig = parseInt(kader.style.height, 10) || kader.clientHeight || 0;

        if (Math.abs(hoogte - huidig) <= TOLERANTIE) {
            return;
        }

        kader.style.height = hoogte + 'px';
        kader.style.minHeight = '0';
    }

    /**
     * Deze pagina naar de betaalpagina sturen.
     *
     * Pay.nl weigert in een kader te staan - X-Frame-Options - dus de betaling
     * moet het hele venster hebben. Het kader mag deze pagina daar niet zelf
     * naartoe sturen: een browser blokkeert dat, want zo kaapt een advertentie
     * de pagina waar hij in staat. Wij zijn de pagina zelf, en een pagina mag
     * altijd ergens anders naartoe.
     *
     * Alleen https, en alleen van onze eigen winkel (de aanroeper controleert
     * dat): daarmee is dit geen open doorverwijzing voor iedereen die het
     * bericht kent.
     */
    function betaal(adres) {
        if (typeof adres !== 'string' || adres.length > 2000) {
            return;
        }

        var anker = document.createElement('a');
        anker.href = adres;

        if (anker.protocol !== 'https:') {
            return;
        }

        // Vervangen en niet gewoon openen. Terug vanaf de betaalpagina zou
        // anders hier uitkomen, en dan hangt het kader op het antwoord van een
        // formulier dat al verstuurd is - de browser vraagt of het nog eens
        // verstuurd mag worden, en dat is een tweede bestelling. Zo komt terug
        // uit op de pagina van vóór de kaartverkoop, en staat de weg terug naar
        // de club op de bedankpagina.
        window.location.replace(anker.href);
    }

    window.addEventListener('message', function (bericht) {
        var gegevens = bericht.data;

        if (!gegevens || typeof gegevens !== 'object') {
            return;
        }

        var kader = afzender(bericht.source);

        if (!kader || !zelfdeHerkomst(kader, bericht.origin)) {
            return;
        }

        if (gegevens.voetbalplannerShopBetaling) {
            betaal(gegevens.voetbalplannerShopBetaling);

            return;
        }

        if (gegevens.voetbalplannerShopHoogte) {
            groei(kader, gegevens.voetbalplannerShopHoogte);
        }
    });
})();
