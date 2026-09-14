<?php
/**
 * Plugin Name:       VoetbalPlanner Ticketshop
 * Plugin URI:        https://voetbalplanner.nl
 * Description:       Zet de kaartverkoop van je club op je eigen website met de shortcode [voetbalplanner_ticketshop].
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            VoetbalPlanner
 * License:           GPL-2.0-or-later
 * Text Domain:       voetbalplanner-ticketshop
 */

// Rechtstreeks opvragen van dit bestand levert niets op.
if (! defined('ABSPATH')) {
    exit;
}

define('VP_TICKETSHOP_VERSIE', '1.0.0');
define('VP_TICKETSHOP_BESTAND', __FILE__);

/** De winkel draait op voetbalplanner.nl, tenzij de beheerder iets anders invult. */
function vp_ticketshop_standaard_basis()
{
    return 'https://voetbalplanner.nl';
}

/**
 * De ingestelde waarden, al schoongemaakt.
 *
 * @return array{basis:string, club:string, hoogte:int}
 */
function vp_ticketshop_instellingen()
{
    return [
        'basis'  => vp_ticketshop_schoon_basis(get_option('vp_ticketshop_basis', vp_ticketshop_standaard_basis())),
        'club'   => vp_ticketshop_schoon_club(get_option('vp_ticketshop_club', '')),
        'hoogte' => vp_ticketshop_schoon_hoogte(get_option('vp_ticketshop_hoogte', 900)),
    ];
}

/**
 * De clubnaam in het adres. De routes van de winkel laten alleen kleine
 * letters, cijfers en streepjes toe, dus alles daarbuiten gooien we weg in
 * plaats van het door te geven en een 404 te krijgen.
 */
function vp_ticketshop_schoon_club($club)
{
    $club = strtolower(trim((string) $club));
    $club = preg_replace('/[^a-z0-9-]/', '', $club);

    return trim((string) $club, '-');
}

/** Het adres van de winkel, zonder schuine streep aan het eind. */
function vp_ticketshop_schoon_basis($basis)
{
    $basis = esc_url_raw(trim((string) $basis));

    if ($basis === '') {
        $basis = vp_ticketshop_standaard_basis();
    }

    return untrailingslashit($basis);
}

/**
 * De starthoogte van het kader. Het iframe groeit vanzelf mee zodra de winkel
 * zijn hoogte doorgeeft; dit is alleen wat er staat tot dat bericht binnen is.
 */
function vp_ticketshop_schoon_hoogte($hoogte)
{
    $hoogte = (int) $hoogte;

    if ($hoogte < 200) {
        $hoogte = 200;
    }

    if ($hoogte > 5000) {
        $hoogte = 5000;
    }

    return $hoogte;
}

/**
 * De shortcode: [voetbalplanner_ticketshop club="bon-boys" hoogte="900"]
 *
 * Zonder club valt hij terug op wat er bij Instellingen staat.
 */
function vp_ticketshop_shortcode($attributen = [])
{
    $instellingen = vp_ticketshop_instellingen();

    $attributen = shortcode_atts(
        [
            'club'   => $instellingen['club'],
            'hoogte' => $instellingen['hoogte'],
            'basis'  => $instellingen['basis'],
            'titel'  => __('Kaartverkoop', 'voetbalplanner-ticketshop'),
        ],
        $attributen,
        'voetbalplanner_ticketshop'
    );

    $club = vp_ticketshop_schoon_club($attributen['club']);

    // Zonder club is er niets te tonen. Een bezoeker hoeft daar niets van te
    // merken; de beheerder wel, want die kan het oplossen.
    if ($club === '') {
        if (! current_user_can('manage_options')) {
            return '';
        }

        return '<p class="vp-ticketshop-melding">'
            . esc_html__('VoetbalPlanner Ticketshop: vul eerst de clubnaam in bij Instellingen - Ticketshop, of geef hem mee als club="..." in de shortcode.', 'voetbalplanner-ticketshop')
            . '</p>';
    }

    $basis  = vp_ticketshop_schoon_basis($attributen['basis']);
    $hoogte = vp_ticketshop_schoon_hoogte($attributen['hoogte']);
    $bron   = $basis . '/' . $club . '/ticketshop?embed=1';

    wp_enqueue_script('vp-ticketshop');

    return sprintf(
        '<div class="vp-ticketshop">'
        . '<iframe class="vp-ticketshop-kader" src="%1$s" title="%2$s" height="%3$d" loading="lazy"'
        . ' style="border:0;width:100%%;display:block;min-height:%3$dpx"'
        . ' allow="payment" referrerpolicy="no-referrer-when-downgrade"></iframe>'
        . '<noscript><p><a href="%4$s" target="_blank" rel="noopener">%5$s</a></p></noscript>'
        . '</div>',
        esc_url($bron),
        esc_attr($attributen['titel']),
        $hoogte,
        esc_url($basis . '/' . $club . '/ticketshop'),
        esc_html__('Open de kaartverkoop', 'voetbalplanner-ticketshop')
    );
}
add_shortcode('voetbalplanner_ticketshop', 'vp_ticketshop_shortcode');

/**
 * Het script dat het iframe laat meegroeien. Alleen registreren: de shortcode
 * zet hem pas in de rij als er ook echt een winkel op de pagina staat.
 */
function vp_ticketshop_registreer_script()
{
    wp_register_script(
        'vp-ticketshop',
        plugins_url('assets/ticketshop.js', VP_TICKETSHOP_BESTAND),
        [],
        VP_TICKETSHOP_VERSIE,
        true
    );
}
add_action('wp_enqueue_scripts', 'vp_ticketshop_registreer_script');

/* -------------------------------------------------------------------------
 | Beheerscherm
 | ---------------------------------------------------------------------- */

function vp_ticketshop_menu()
{
    add_options_page(
        __('VoetbalPlanner Ticketshop', 'voetbalplanner-ticketshop'),
        __('Ticketshop', 'voetbalplanner-ticketshop'),
        'manage_options',
        'vp-ticketshop',
        'vp_ticketshop_scherm'
    );
}
add_action('admin_menu', 'vp_ticketshop_menu');

function vp_ticketshop_velden()
{
    register_setting('vp_ticketshop', 'vp_ticketshop_club', [
        'type'              => 'string',
        'sanitize_callback' => 'vp_ticketshop_schoon_club',
        'default'           => '',
    ]);

    register_setting('vp_ticketshop', 'vp_ticketshop_basis', [
        'type'              => 'string',
        'sanitize_callback' => 'vp_ticketshop_schoon_basis',
        'default'           => vp_ticketshop_standaard_basis(),
    ]);

    register_setting('vp_ticketshop', 'vp_ticketshop_hoogte', [
        'type'              => 'integer',
        'sanitize_callback' => 'vp_ticketshop_schoon_hoogte',
        'default'           => 900,
    ]);
}
add_action('admin_init', 'vp_ticketshop_velden');

function vp_ticketshop_scherm()
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $instellingen = vp_ticketshop_instellingen();
    $winkelUrl    = $instellingen['club'] !== ''
        ? $instellingen['basis'] . '/' . $instellingen['club'] . '/ticketshop'
        : '';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('VoetbalPlanner Ticketshop', 'voetbalplanner-ticketshop'); ?></h1>

        <p><?php esc_html_e('Zet de kaartverkoop op een pagina met deze shortcode:', 'voetbalplanner-ticketshop'); ?>
            <code>[voetbalplanner_ticketshop]</code></p>

        <form action="options.php" method="post">
            <?php settings_fields('vp_ticketshop'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="vp_ticketshop_club"><?php esc_html_e('Clubnaam in het adres', 'voetbalplanner-ticketshop'); ?></label>
                    </th>
                    <td>
                        <input name="vp_ticketshop_club" id="vp_ticketshop_club" type="text" class="regular-text"
                               value="<?php echo esc_attr($instellingen['club']); ?>" placeholder="bon-boys">
                        <p class="description">
                            <?php esc_html_e('Het stukje uit voetbalplanner.nl/.../ticketshop, bijvoorbeeld bon-boys.', 'voetbalplanner-ticketshop'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="vp_ticketshop_hoogte"><?php esc_html_e('Starthoogte', 'voetbalplanner-ticketshop'); ?></label>
                    </th>
                    <td>
                        <input name="vp_ticketshop_hoogte" id="vp_ticketshop_hoogte" type="number" min="200" max="5000" step="10"
                               value="<?php echo esc_attr($instellingen['hoogte']); ?>"> px
                        <p class="description">
                            <?php esc_html_e('Het kader groeit vanzelf mee met de winkel. Dit is alleen de hoogte tot dat gebeurt.', 'voetbalplanner-ticketshop'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="vp_ticketshop_basis"><?php esc_html_e('Adres van VoetbalPlanner', 'voetbalplanner-ticketshop'); ?></label>
                    </th>
                    <td>
                        <input name="vp_ticketshop_basis" id="vp_ticketshop_basis" type="url" class="regular-text"
                               value="<?php echo esc_attr($instellingen['basis']); ?>">
                        <p class="description">
                            <?php esc_html_e('Laat dit staan, tenzij je club op een eigen adres draait.', 'voetbalplanner-ticketshop'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <?php if ($winkelUrl !== '') : ?>
            <h2><?php esc_html_e('Controleren', 'voetbalplanner-ticketshop'); ?></h2>
            <p>
                <?php esc_html_e('Jouw winkel staat hier:', 'voetbalplanner-ticketshop'); ?>
                <a href="<?php echo esc_url($winkelUrl); ?>" target="_blank" rel="noopener"><?php echo esc_html($winkelUrl); ?></a>
            </p>
            <p>
                <?php esc_html_e('Een andere club op een losse pagina kan ook:', 'voetbalplanner-ticketshop'); ?>
                <code>[voetbalplanner_ticketshop club="<?php echo esc_attr($instellingen['club']); ?>" hoogte="1200"]</code>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

/** Een regel naar de instellingen op het pluginoverzicht. */
function vp_ticketshop_actielink($links)
{
    $link = '<a href="' . esc_url(admin_url('options-general.php?page=vp-ticketshop')) . '">'
        . esc_html__('Instellingen', 'voetbalplanner-ticketshop') . '</a>';

    array_unshift($links, $link);

    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'vp_ticketshop_actielink');
