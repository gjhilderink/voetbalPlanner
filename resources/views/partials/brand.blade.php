{{--
    De huisstijl van VoetbalPlanner, op één plek.

    Groen is de hoofdkleur, rood is een accent en verder niets: één rode streep
    of één rode badge per scherm. Wie rood als tweede hoofdkleur gebruikt krijgt
    een pagina die schreeuwt, en dan valt het accent nergens meer op.

    Deze partial hoort in de <head> van elke publieke pagina. Hij zet de
    lettertypes, kleurt Tailwind om (de site draait op de CDN-build, dus dat kan
    hier) en levert een handvol klassen voor dingen die Tailwind niet heeft: het
    woordmerk, de tactiekbord-achtergrond en de diagonale hoek uit het icoon.
--}}

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;1,700;1,800&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="icon" type="image/png" href="{{ asset('brand/favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('brand/app-icon-180.png') }}">

<script>
    // Draait alleen op de CDN-build van Tailwind. Bestaat er ooit een
    // gecompileerde build, dan hoort dit in tailwind.config.js te staan; de
    // kleuren en namen hieronder zijn dan één-op-één over te nemen.
    if (window.tailwind) {
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // De hoofdkleur, uit het woordmerk op de poster.
                        brand: {
                            50:  '#F1F8EC', 100: '#DEEFD1', 200: '#BFE0A8',
                            300: '#9BCE7B', 400: '#7BBA53', 500: '#5BA12F',
                            600: '#4A8526', 700: '#3A691E', 800: '#2C5017',
                            900: '#1F3910',
                        },
                        // Het accent. Uit het app-icoon, niet uit de donkerrode
                        // hoek van de poster: die is te dof om naast groen op te
                        // vallen.
                        accent: { 500: '#E63027', 600: '#C5211A' },
                        // De donkere ondergrond van de poster.
                        navy: {
                            700: '#203449', 800: '#14283D', 900: '#0B1D31',
                        },
                    },
                    fontFamily: {
                        sans: ['Barlow', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        display: ['"Barlow Condensed"', 'Barlow', 'sans-serif'],
                    },
                },
            },
        };
    }
</script>

<style>
    :root {
        --brand:        #5BA12F;
        --brand-dark:   #4A8526;
        --accent:       #E63027;
        --navy:         #0B1D31;
        --navy-mid:     #14283D;
        --navy-soft:    #203449;
    }

    body { font-family: 'Barlow', ui-sans-serif, system-ui, sans-serif; }

    /* Koppen: smal, zwaar en cursief — de vorm van het woordmerk. */
    .display {
        font-family: 'Barlow Condensed', 'Barlow', sans-serif;
        font-weight: 800;
        font-style: italic;
        letter-spacing: -0.01em;
        line-height: 0.95;
        text-transform: uppercase;
    }

    /* De payoff onder het woordmerk: gespatieerd en klein. */
    .payoff {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.18em;
    }

    /* Het woordmerk zelf, als tekst en niet als plaatje: dan blijft het scherp
       op elk scherm en leesbaar voor een schermlezer. */
    .wordmark {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        font-style: italic;
        text-transform: uppercase;
        line-height: 0.88;
        letter-spacing: -0.015em;
    }
    .wordmark .groen { color: var(--brand); }
    .wordmark .tld   { font-size: 0.55em; letter-spacing: 0; }

    /* Tactiekbord: de stippellijnen en kruisjes van de poster, als patroon in
       plaats van als foto. Scheelt een megabyte en schaalt naar elk formaat. */
    .tactisch {
        background-color: var(--navy);
        background-image:
            radial-gradient(circle at 18% 22%, rgba(91,161,47,.18), transparent 42%),
            radial-gradient(circle at 82% 12%, rgba(230,48,39,.10), transparent 38%),
            repeating-linear-gradient(115deg, rgba(255,255,255,.045) 0 1px, transparent 1px 46px),
            repeating-linear-gradient(-115deg, rgba(255,255,255,.035) 0 1px, transparent 1px 62px);
    }

    /* De diagonale hoek uit het app-icoon: rood-wit-groen, als signatuur boven
       een sectie. Eén keer per pagina; het is een accent, geen decoratie. */
    .hoekstreep {
        height: 6px;
        background: linear-gradient(90deg,
            var(--accent) 0 22%, #fff 22% 26%, var(--brand) 26% 100%);
    }

    .btn-brand {
        background: var(--brand);
        color: #fff;
        transition: background-color .15s ease;
    }
    .btn-brand:hover { background: var(--brand-dark); }

    /* Groen onderstreept een kopwoord; dat is waar de kleur het werk doet. */
    .streep {
        background-image: linear-gradient(var(--brand), var(--brand));
        background-size: 100% .14em;
        /* Onder de letters en niet erdoorheen: .display heeft een krappe
           regelafstand, dus zonder die extra ruimte eronder loopt de streep
           dwars door de onderkant van de tekst. */
        background-position: 0 100%;
        background-repeat: no-repeat;
        padding-bottom: .14em;
    }
</style>
