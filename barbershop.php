<?php
/*
Plugin Name: Barbershop
Description: Rôles, prestations et réservations pour la gestion du barbershop.
Version: 1.0.0
Author: Yannick Zohou
Text Domain: barbershop
*/

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'barbershop_enqueue_assets');
function barbershop_enqueue_assets() {
    // CSS principal du plugin (optionnel, si tu as un barbershop.css)
    if (file_exists(plugin_dir_path(__FILE__) . 'assets/barbershop.css')) {
        wp_enqueue_style(
            'barbershop-style',
            plugin_dir_url(__FILE__) . 'assets/barbershop.css',
            [],
            '1.0.0'
        );
    }
    wp_enqueue_style('dashicons');
}


/**
 * Capabilities personnalisées pour les rôles
 */
function barbershop_get_custom_caps() {
    return [
        'collaborateur' => [
            'read'                         => true,
            'barbershop_read_reservations' => true,  // consulter les réservations
        ],
        'manager' => [
            'read'                               => true,
            'barbershop_read_reservations'       => true,  // voir la liste
            'barbershop_manage_all_reservations' => true,  // créer / éditer / supprimer

            // Gestion des utilisateurs
            'list_users'   => true,
            'edit_users'   => true,
            'delete_users' => true,
            'create_users' => true,
        ],
    ];
}

/**
 * Création / MAJ des rôles à l’activation du plugin
 */
function barbershop_activate() {
    $caps = barbershop_get_custom_caps();

    // Rôle collaborateur
    $collab_role = get_role('collaborateur');
    if (!$collab_role) {
        $collab_role = add_role(
            'collaborateur',
            'Collaborateur',
            [
                'read' => true,
            ]
        );
    }
    if ($collab_role) {
        foreach ($caps['collaborateur'] as $cap => $grant) {
            $collab_role->add_cap($cap, $grant);
        }
    }

    // Rôle manager
    $manager_role = get_role('manager');
    if (!$manager_role) {
        $manager_role = add_role(
            'manager',
            'Manager',
            [
                'read' => true,
            ]
        );
    }
    if ($manager_role) {
        foreach ($caps['manager'] as $cap => $grant) {
            $manager_role->add_cap($cap, $grant);
        }
    }

    if ($admin_role = get_role('administrator')) {
        foreach ($caps['collaborateur'] as $cap => $grant) {
            $admin_role->add_cap($cap, $grant);
        }
        foreach ($caps['manager'] as $cap => $grant) {
            $admin_role->add_cap($cap, $grant);
        }
    }
}
register_activation_hook(__FILE__, 'barbershop_activate');

/**
 * À la désactivation : on enlève juste les caps custom
 */
function barbershop_deactivate() {
    $caps = barbershop_get_custom_caps();

    if ($collab_role = get_role('collaborateur')) {
        foreach ($caps['collaborateur'] as $cap => $grant) {
            $collab_role->remove_cap($cap);
        }
    }

    if ($manager_role = get_role('manager')) {
        foreach ($caps['manager'] as $cap => $grant) {
            $manager_role->remove_cap($cap);
        }
    }
}
register_deactivation_hook(__FILE__, 'barbershop_deactivate');

/**
 * CPT Prestations & Réservations + taxonomie de catégories de prestations
 */
add_action('init', 'barbershop_register_post_types');
function barbershop_register_post_types() {

    // CPT Prestations
    register_post_type('bs_prestation', [
        'labels' => [
            'name'               => 'Prestations',
            'singular_name'      => 'Prestation',
            'add_new_item'       => 'Ajouter une prestation',
            'edit_item'          => 'Modifier la prestation',
            'new_item'           => 'Nouvelle prestation',
            'view_item'          => 'Voir la prestation',
            'search_items'       => 'Rechercher une prestation',
            'not_found'          => 'Aucune prestation trouvée',
            'not_found_in_trash' => 'Aucune prestation trouvée dans la corbeille',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-clipboard',
        'supports'     => ['title', 'editor'],
        'has_archive'  => false,
        'show_in_rest' => true,
    ]);

    // Taxonomie catégories de prestation
    register_taxonomy('bs_prestation_category', 'bs_prestation', [
        'labels' => [
            'name'              => 'Catégories de prestations',
            'singular_name'     => 'Catégorie de prestation',
            'search_items'      => 'Rechercher une catégorie',
            'all_items'         => 'Toutes les catégories',
            'edit_item'         => 'Modifier la catégorie',
            'update_item'       => 'Mettre à jour la catégorie',
            'add_new_item'      => 'Ajouter une nouvelle catégorie',
            'new_item_name'     => 'Nom de la nouvelle catégorie',
            'menu_name'         => 'Catégories de prestations',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
    ]);

    // CPT Réservations
    // CPT Réservations
    register_post_type('bs_reservation', [
        'labels' => [
            'name'               => 'Réservations',
            'singular_name'      => 'Réservation',
            'add_new_item'       => 'Ajouter une réservation',
            'edit_item'          => 'Modifier la réservation',
            'new_item'           => 'Nouvelle réservation',
            'view_item'          => 'Voir la réservation',
            'search_items'       => 'Rechercher une réservation',
            'not_found'          => 'Aucune réservation trouvée',
            'not_found_in_trash' => 'Aucune réservation trouvée dans la corbeille',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-calendar-alt',
        'supports'     => ['title'],
        'has_archive'  => false,
        'show_in_rest' => false,

        // On ne touche PAS à map_meta_cap / capability_type,
        // on mappe juste ce que WP regarde dans l’admin.
        'capabilities' => [
            // Liste des réservations / menu
            'edit_posts'           => 'barbershop_read_reservations',

            // Création / publication → managers seulement
            'publish_posts'        => 'barbershop_manage_all_reservations',
            'create_posts'         => 'barbershop_manage_all_reservations',

            // Lecture de la liste privée
            'read_private_posts'   => 'barbershop_manage_all_reservations',

            // Édition / suppression → managers seulement
            'edit_post'            => 'barbershop_manage_all_reservations',
            'edit_others_posts'    => 'barbershop_manage_all_reservations',
            'edit_published_posts' => 'barbershop_manage_all_reservations',
            'delete_post'          => 'barbershop_manage_all_reservations',
            'delete_posts'         => 'barbershop_manage_all_reservations',
            'delete_others_posts'  => 'barbershop_manage_all_reservations',
            'delete_private_posts' => 'barbershop_manage_all_reservations',
            'delete_published_posts' => 'barbershop_manage_all_reservations',

            // Lecture basique
            'read'                 => 'read',
        ],
    ]);

}



/**
 * Metaboxes pour Prestations et Réservations
 */
add_action('add_meta_boxes', 'barbershop_add_meta_boxes');
function barbershop_add_meta_boxes() {

    add_meta_box(
        'barbershop_prestation_meta',
        'Détails de la prestation',
        'barbershop_render_prestation_meta',
        'bs_prestation',
        'normal',
        'default'
    );

    add_meta_box(
        'barbershop_reservation_meta',
        'Détails de la réservation',
        'barbershop_render_reservation_meta',
        'bs_reservation',
        'normal',
        'default'
    );

}

/**
 * Metabox Prestation : tarif + durée
 */
function barbershop_render_prestation_meta($post) {
    wp_nonce_field('barbershop_save_prestation_meta', 'barbershop_prestation_nonce');

    $price    = get_post_meta($post->ID, '_barbershop_prestation_price', true);
    $duration = get_post_meta($post->ID, '_barbershop_prestation_duration', true);
    // Récup méta déjà enregistrée
    $saved_staff_ids = get_post_meta($post->ID, '_barbershop_prestation_staff_ids', true);
    ?>
    <p>
        <label for="barbershop_prestation_price"><strong>Tarif</strong></label><br>
        <input type="text"
               id="barbershop_prestation_price"
               name="barbershop_prestation_price"
               value="<?php echo esc_attr($price); ?>"
               style="width:100%;"
               placeholder="Ex : 20€ ou dès 120€">
    </p>
    <p>
        <label for="barbershop_prestation_duration"><strong>Durée (en minutes)</strong></label><br>
        <input type="number"
               id="barbershop_prestation_duration"
               name="barbershop_prestation_duration"
               value="<?php echo esc_attr($duration); ?>"
               style="width:100%;max-width:160px;"
               min="0"
               step="5"
               placeholder="Ex : 45">
        <span class="description">Durée indicative de la prestation (servira au planning plus tard).</span>
    </p>
    <?php

    // Récupérer les collaborateurs existants
    $staff_query = get_users([
        'role__in' => ['collaborateur', 'manager'],
        'orderby'  => 'display_name',
        'order'    => 'ASC',
    ]);

    if (!is_array($saved_staff_ids)) {
        $saved_staff_ids = [];
    }



    if (empty($staff_query)) {
        echo '<p>' . esc_html__('Aucun collaborateur défini.', 'barbershop') . '</p>';
        return;
    }

    echo '<p style="margin-bottom: .5rem;">';
    echo esc_html__('Laissez tout décoché pour autoriser tous les collaborateurs.', 'barbershop');
    echo '</p>';

    echo '<div style="max-height: 220px; overflow:auto; border:1px solid #e5e7eb; padding:.5rem; border-radius:4px;">';

    foreach ($staff_query as $staff) {
        $id = $staff->ID;
        $name = $staff->first_name;

        ?>
        <label style="display:block;margin-bottom:.35rem;">
            <input type="checkbox"
                   name="barbershop_prestation_staff_ids[]"
                   value="<?php echo esc_attr($id); ?>"
                <?php checked(in_array($id, $saved_staff_ids, true)); ?>
            >
            <?php echo esc_html($name); ?>
        </label>
        <?php
    }

    echo '</div>';
}

/**
 * Metabox Réservation : date, heure, prestation, collaborateur
 */
function barbershop_render_reservation_meta($post) {
    wp_nonce_field('barbershop_save_reservation_meta', 'barbershop_reservation_nonce');

    $prestation_id   = get_post_meta($post->ID, '_barbershop_reservation_prestation_id', true);
    $collaborator_id = get_post_meta($post->ID, '_barbershop_reservation_collaborator_id', true);
    $date            = get_post_meta($post->ID, '_barbershop_reservation_date', true);  // format Y-m-d
    $time            = get_post_meta($post->ID, '_barbershop_reservation_time', true);  // format HH:MM
    $duration        = get_post_meta($post->ID, '_barbershop_reservation_duration', true); // minutes (copie de la prestation au moment de la resa)


    // Vérification : pas de double réservation pour ce collaborateur sur ce créneau
    if ($collaborator_id > 0 && !empty($date) && !empty($time) && $duration > 0) {
        $slot_ok = barbershop_is_slot_available_for_reservation(
            $post->ID,
            $date,
            $time,
            $duration,
            $collaborator_id
        );

        if (!$slot_ok) {
            // On empêche la mise à jour du créneau et on informe l'admin via une notice
            set_transient('barbershop_reservation_conflict_' . $post->ID, 1, 60);
            return;
        }
    }


    // Liste des prestations
    $prestations = get_posts([
        'post_type'      => 'bs_prestation',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    // Liste des collaborateurs & managers
    $collaborators = get_users([
        'role__in' => ['collaborateur', 'manager'],
        'orderby'  => 'display_name',
        'order'    => 'ASC',
    ]);
    ?>
    <p>
        <label for="barbershop_reservation_prestation_id"><strong>Prestation</strong></label><br>
        <select id="barbershop_reservation_prestation_id" name="barbershop_reservation_prestation_id" style="width:100%;max-width:320px;">
            <option value="">— Sélectionner une prestation —</option>
            <?php foreach ($prestations as $p) : ?>
                <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($prestation_id, $p->ID); ?>>
                    <?php echo esc_html($p->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="barbershop_reservation_collaborator_id"><strong>Collaborateur / Manager</strong></label><br>
        <select id="barbershop_reservation_collaborator_id" name="barbershop_reservation_collaborator_id" style="width:100%;max-width:320px;">
            <option value="">— Sans préférence (non assigné)</option>
            <?php foreach ($collaborators as $user) : ?>
                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($collaborator_id, $user->ID); ?>>
                    <?php echo esc_html($user->display_name . ' (' . implode(', ', $user->roles) . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="description">Laisser vide pour une réservation sans préférence.</span>
    </p>

    <p>
        <label for="barbershop_reservation_date"><strong>Date</strong></label><br>
        <input type="date"
               id="barbershop_reservation_date"
               name="barbershop_reservation_date"
               value="<?php echo esc_attr($date); ?>">
    </p>

    <p>
        <label for="barbershop_reservation_time"><strong>Heure de début</strong></label><br>
        <input type="time"
               id="barbershop_reservation_time"
               name="barbershop_reservation_time"
               value="<?php echo esc_attr($time); ?>">
    </p>

    <p>
        <label for="barbershop_reservation_duration"><strong>Durée (minutes)</strong></label><br>
        <input type="number"
               id="barbershop_reservation_duration"
               name="barbershop_reservation_duration"
               value="<?php echo esc_attr($duration); ?>"
               style="width:100%;max-width:160px;"
               min="0"
               step="5">
        <span class="description">
            Copie de la durée de la prestation au moment de la réservation.
            (Servira plus tard pour empêcher les chevauchements de créneaux.)
        </span>
    </p>
    <?php
}

/**
 * Sauvegarde des metas Prestation
 */
add_action('save_post_bs_prestation', 'barbershop_save_prestation_meta');
function barbershop_save_prestation_meta($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!isset($_POST['barbershop_prestation_nonce']) || !wp_verify_nonce($_POST['barbershop_prestation_nonce'], 'barbershop_save_prestation_meta')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $price    = isset($_POST['barbershop_prestation_price']) ? sanitize_text_field($_POST['barbershop_prestation_price']) : '';
    $duration = isset($_POST['barbershop_prestation_duration']) ? intval($_POST['barbershop_prestation_duration']) : 0;

    update_post_meta($post_id, '_barbershop_prestation_price', $price);
    update_post_meta($post_id, '_barbershop_prestation_duration', $duration);

    $staff_ids = isset($_POST['barbershop_prestation_staff_ids'])
        ? array_map('intval', (array) $_POST['barbershop_prestation_staff_ids'])
        : [];

    if (empty($staff_ids)) {
        // Aucun collaborateur sélectionné = tous autorisés
        delete_post_meta($post_id, '_barbershop_prestation_staff_ids');
    } else {
        update_post_meta($post_id, '_barbershop_prestation_staff_ids', $staff_ids);
    }
}

/**
 * Sauvegarde des metas Réservation
 */
add_action('save_post_bs_reservation', 'barbershop_save_reservation_meta');
function barbershop_save_reservation_meta($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!isset($_POST['barbershop_reservation_nonce']) || !wp_verify_nonce($_POST['barbershop_reservation_nonce'], 'barbershop_save_reservation_meta')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $prestation_id   = isset($_POST['barbershop_reservation_prestation_id']) ? intval($_POST['barbershop_reservation_prestation_id']) : 0;
    $collaborator_id = isset($_POST['barbershop_reservation_collaborator_id']) ? intval($_POST['barbershop_reservation_collaborator_id']) : 0;
    $date            = isset($_POST['barbershop_reservation_date']) ? sanitize_text_field($_POST['barbershop_reservation_date']) : '';
    $time            = isset($_POST['barbershop_reservation_time']) ? sanitize_text_field($_POST['barbershop_reservation_time']) : '';
    $duration        = isset($_POST['barbershop_reservation_duration']) ? intval($_POST['barbershop_reservation_duration']) : 0;

    update_post_meta($post_id, '_barbershop_reservation_prestation_id', $prestation_id);
    update_post_meta($post_id, '_barbershop_reservation_collaborator_id', $collaborator_id);
    update_post_meta($post_id, '_barbershop_reservation_date', $date);
    update_post_meta($post_id, '_barbershop_reservation_time', $time);
    update_post_meta($post_id, '_barbershop_reservation_duration', $duration);
}

/**
 * Réglages du planning Barbershop
 * - horaires d'ouverture
 * - intervalle des créneaux
 * - nombre de jours affichés
 */

function barbershop_get_planning_defaults() {
    return [
        'days_ahead'        => 14,        // nombre de jours à afficher
        'open_time'         => '10:00',   // (fallback) heure d'ouverture globale
        'close_time'        => '20:00',   // (fallback) heure de fermeture globale
        'slot_interval'     => 30,        // minutes (15, 30, 60, etc.)
        'open_days'         => ['1','2','3','4','5','6', '7'], // fallback 1=lundi ... 7=dimanche
        'min_delay_minutes' => 180,
        'day_hours'         => [
            // 1 = lundi, etc.
            '1' => ['enabled' => false,  'open' => '10:00', 'close' => '20:00'],
            '2' => ['enabled' => true,  'open' => '10:00', 'close' => '20:00'],
            '3' => ['enabled' => true,  'open' => '10:00', 'close' => '20:00'],
            '4' => ['enabled' => true,  'open' => '10:00', 'close' => '20:00'],
            '5' => ['enabled' => true,  'open' => '10:00', 'close' => '20:00'],
            '6' => ['enabled' => true,  'open' => '10:00', 'close' => '20:00'],
            '7' => ['enabled' => true, 'open' => '11:00', 'close' => '19:00'],
        ],
    ];
}


function barbershop_get_planning_settings() {
    $defaults = barbershop_get_planning_defaults();
    $settings = get_option('barbershop_planning', []);

    if (!is_array($settings)) {
        $settings = [];
    }

    $settings = wp_parse_args($settings, $defaults);

    // Normalisation de base
    $settings['days_ahead']        = max(1, (int) $settings['days_ahead']);
    $settings['slot_interval']     = max(5, (int) $settings['slot_interval']);
    $settings['min_delay_minutes'] = max(0, (int) $settings['min_delay_minutes']);

    // Normaliser open_days (fallback)
    $settings['open_days'] = array_map('strval', (array) $settings['open_days']);

    // Normaliser day_hours
    if (empty($settings['day_hours']) || !is_array($settings['day_hours'])) {
        $settings['day_hours'] = [];
    }

    $normalized_day_hours = [];
    foreach (['1','2','3','4','5','6','7'] as $day_num) {
        $conf = isset($settings['day_hours'][$day_num]) && is_array($settings['day_hours'][$day_num])
            ? $settings['day_hours'][$day_num]
            : [];

        $enabled = isset($conf['enabled']) ? (bool) $conf['enabled'] : in_array($day_num, $settings['open_days'], true);
        $open    = !empty($conf['open'])  ? $conf['open']  : $settings['open_time'];
        $close   = !empty($conf['close']) ? $conf['close'] : $settings['close_time'];

        // Sécurité simple sur le format HH:MM
        if (!preg_match('/^\d{2}:\d{2}$/', $open)) {
            $open = $defaults['open_time'];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $close)) {
            $close = $defaults['close_time'];
        }

        $normalized_day_hours[$day_num] = [
            'enabled' => $enabled,
            'open'    => $open,
            'close'   => $close,
        ];
    }

    $settings['day_hours'] = $normalized_day_hours;
    $settings['open_days'] = array_values(
        array_filter(
            array_keys($settings['day_hours']),
            fn($d) => !empty($settings['day_hours'][$d]['enabled'])
        )
    );

    return $settings;
}


/**
 * Vérifie si un créneau est disponible pour un collaborateur donné.
 *
 * On considère qu'il y a conflit si deux réservations se chevauchent
 * dans le temps pour le même collaborateur.
 *
 * @param string $date      Date au format Y-m-d
 * @param string $time      Heure de début au format H:i (ex : 14:30)
 * @param int    $duration  Durée en minutes
 * @param int    $staff_id  ID du collaborateur (0 = on ne filtre pas par collaborateur)
 *
 * @return bool  true si disponible, false si déjà pris
 */
function barbershop_is_slot_available($date, $time, $duration, $staff_id = 0) {
    $date  = trim($date);
    $time  = trim($time);
    $duration = (int) $duration;

    if (empty($date) || empty($time) || $duration <= 0) {
        // On préfère considérer indisponible si données incohérentes
        return false;
    }

    $start_ts = strtotime($date . ' ' . $time);
    if (!$start_ts) {
        return false;
    }
    $end_ts = $start_ts + ($duration * 60);

    // On récupère toutes les réservations de ce jour (et éventuellement de ce collaborateur)
    $meta_query = [
        'relation' => 'AND',
        [
            'key'     => '_barbershop_reservation_date',
            'value'   => $date,
            'compare' => '=',
        ],
    ];

    // Si on a un collaborateur précis, on filtre dessus
    if ($staff_id > 0) {
        $meta_query[] = [
            'key'     => '_barbershop_reservation_collaborator_id',
            'value'   => $staff_id,
            'compare' => '=',
        ];
    }

    $existing_ids = get_posts([
        'post_type'      => 'bs_reservation',
        'post_status'    => ['publish', 'pending'],
        'posts_per_page' => -1,
        'meta_query'     => $meta_query,
        'fields'         => 'ids',
    ]);

    if (empty($existing_ids)) {
        return true; // rien ce jour-là
    }

    foreach ($existing_ids as $res_id) {
        $res_time = get_post_meta($res_id, '_barbershop_reservation_time', true);
        $res_dur  = (int) get_post_meta($res_id, '_barbershop_reservation_duration', true);

        if (empty($res_time)) {
            continue;
        }
        if ($res_dur <= 0) {
            // Si aucune durée stockée, on considère au minimum un bloc
            $res_dur = $duration;
        }

        $res_start_ts = strtotime($date . ' ' . $res_time);
        if (!$res_start_ts) {
            continue;
        }
        $res_end_ts = $res_start_ts + ($res_dur * 60);

        // Si les intervalles se chevauchent -> créneau indisponible
        if ($start_ts < $res_end_ts && $end_ts > $res_start_ts) {
            return false;
        }
    }

    return true;
}

/**
 * Retourne la liste des IDs de collaborateurs autorisés pour une prestation.
 * Si méta vide → tous les collaborateurs sont autorisés.
 */
function barbershop_get_allowed_staff_for_prestation($prestation_id) {
    $ids = get_post_meta($prestation_id, '_barbershop_prestation_staff_ids', true);
    if (!is_array($ids) || empty($ids)) {
        return []; // signifie "tous autorisés"
    }
    return array_map('intval', $ids);
}


/**
 * Vérifie si un créneau est disponible pour l'enregistrement / édition
 * d'une réservation dans l'admin.
 *
 * On exclut la réservation en cours ($reservation_id) pour ne pas
 * se considérer soi-même comme un conflit.
 *
 * @param int    $reservation_id ID du post bs_reservation en cours d'édition
 * @param string $date           Date au format Y-m-d
 * @param string $time           Heure de début au format H:i
 * @param int    $duration       Durée en minutes
 * @param int    $staff_id       ID du collaborateur
 *
 * @return bool true si disponible, false s'il y a chevauchement
 */
function barbershop_is_slot_available_for_reservation($reservation_id, $date, $time, $duration, $staff_id) {
    $date     = trim($date);
    $time     = trim($time);
    $duration = (int) $duration;
    $staff_id = (int) $staff_id;

    // Si pas de collaborateur, ou données incomplètes → on ne bloque pas
    if ($staff_id <= 0 || empty($date) || empty($time) || $duration <= 0) {
        return true;
    }

    $start_ts = strtotime($date . ' ' . $time);
    if (!$start_ts) {
        // On préfère bloquer si l'info est incohérente
        return false;
    }
    $end_ts = $start_ts + ($duration * 60);

    // On cherche les autres réservations de ce collaborateur sur cette date
    $meta_query = [
        'relation' => 'AND',
        [
            'key'     => '_barbershop_reservation_date',
            'value'   => $date,
            'compare' => '=',
        ],
        [
            'key'     => '_barbershop_reservation_collaborator_id',
            'value'   => $staff_id,
            'compare' => '=',
        ],
    ];

    $existing_ids = get_posts([
        'post_type'      => 'bs_reservation',
        'post_status'    => ['publish', 'pending'],
        'posts_per_page' => -1,
        'meta_query'     => $meta_query,
        'fields'         => 'ids',
        'post__not_in'   => [$reservation_id], // très important
    ]);

    if (empty($existing_ids)) {
        return true;
    }

    foreach ($existing_ids as $res_id) {
        $res_time = get_post_meta($res_id, '_barbershop_reservation_time', true);
        $res_dur  = (int) get_post_meta($res_id, '_barbershop_reservation_duration', true);

        if (empty($res_time)) {
            continue;
        }
        if ($res_dur <= 0) {
            $res_dur = $duration;
        }

        $res_start_ts = strtotime($date . ' ' . $res_time);
        if (!$res_start_ts) {
            continue;
        }
        $res_end_ts = $res_start_ts + ($res_dur * 60);

        // Test de chevauchement
        if ($start_ts < $res_end_ts && $end_ts > $res_start_ts) {
            return false;
        }
    }

    return true;
}


add_action('admin_menu', 'barbershop_add_planning_page');
function barbershop_add_planning_page() {
    add_options_page(
        'Planning Barbershop',
        'Planning Barbershop',
        'manage_options',
        'barbershop-planning',
        'barbershop_render_planning_page'
    );

}

function barbershop_render_planning_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['barbershop_planning_save'])) {
        check_admin_referer('barbershop_planning_save');

        $settings = barbershop_get_planning_settings();

        // Nombre de jours affichés
        $settings['days_ahead']    = max(1, intval($_POST['days_ahead'] ?? 30));
        $settings['slot_interval'] = max(5, intval($_POST['slot_interval'] ?? 30));

        // Délai minimum
        $min_delay = isset($_POST['min_delay_minutes']) ? (int) $_POST['min_delay_minutes'] : 180;
        if ($min_delay < 0) {
            $min_delay = 0;
        }
        $settings['min_delay_minutes'] = $min_delay;

        // Nouveaux horaires par jour
        $day_hours_input = isset($_POST['day_hours']) && is_array($_POST['day_hours'])
            ? $_POST['day_hours']
            : [];

        $days_labels = [
            '1' => 'Lundi',
            '2' => 'Mardi',
            '3' => 'Mercredi',
            '4' => 'Jeudi',
            '5' => 'Vendredi',
            '6' => 'Samedi',
            '7' => 'Dimanche',
        ];

        $normalized_day_hours = [];
        $open_days = [];

        foreach ($days_labels as $day_num => $label) {
            $row = $day_hours_input[$day_num] ?? [];

            $enabled = !empty($row['enabled']);
            $open    = isset($row['open'])  ? sanitize_text_field($row['open'])  : '';
            $close   = isset($row['close']) ? sanitize_text_field($row['close']) : '';

            // Fallback simple si vide
            if (!preg_match('/^\d{2}:\d{2}$/', $open)) {
                $open = '10:00';
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $close)) {
                $close = '20:00';
            }

            $normalized_day_hours[$day_num] = [
                'enabled' => $enabled,
                'open'    => $open,
                'close'   => $close,
            ];

            if ($enabled) {
                $open_days[] = $day_num;
            }
        }

        $settings['day_hours'] = $normalized_day_hours;
        $settings['open_days'] = $open_days;

        // Pour compat : on peut garder une plage globale "min/max"
        if (!empty($open_days)) {
            $global_open  = '23:59';
            $global_close = '00:00';
            foreach ($open_days as $day_num) {
                $conf = $normalized_day_hours[$day_num];
                if ($conf['open'] < $global_open) {
                    $global_open = $conf['open'];
                }
                if ($conf['close'] > $global_close) {
                    $global_close = $conf['close'];
                }
            }
            $settings['open_time']  = $global_open;
            $settings['close_time'] = $global_close;
        }

        update_option('barbershop_planning', $settings);

        echo '<div class="updated"><p>Réglages enregistrés.</p></div>';
    }

    $s = barbershop_get_planning_settings();
    ?>
    <div class="wrap">
        <h1>Planning Barbershop</h1>
        <form method="post">
            <?php wp_nonce_field('barbershop_planning_save'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="days_ahead">Nombre de jours affichés</label></th>
                    <td>
                        <input type="number" name="days_ahead" id="days_ahead"
                               value="<?php echo esc_attr($s['days_ahead']); ?>" min="1" max="90">
                        <p class="description">Nombre de jours dans le futur affichés dans la grille (mois glissant).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Jours &amp; horaires d'ouverture</th>
                    <td>
                        <?php
                        $days_labels = [
                            '1' => 'Lundi',
                            '2' => 'Mardi',
                            '3' => 'Mercredi',
                            '4' => 'Jeudi',
                            '5' => 'Vendredi',
                            '6' => 'Samedi',
                            '7' => 'Dimanche',
                        ];
                        $day_hours = isset($s['day_hours']) && is_array($s['day_hours']) ? $s['day_hours'] : [];
                        ?>
                        <table class="widefat striped" style="max-width:600px;">
                            <thead>
                            <tr>
                                <th>Jour</th>
                                <th>Ouvert</th>
                                <th>De</th>
                                <th>À</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($days_labels as $day_num => $label) :
                                $conf   = $day_hours[$day_num] ?? ['enabled' => false, 'open' => '10:00', 'close' => '20:00'];
                                $enabled = !empty($conf['enabled']);
                                $open    = $conf['open'] ?? '10:00';
                                $close   = $conf['close'] ?? '20:00';
                                ?>
                                <tr>
                                    <td><?php echo esc_html($label); ?></td>
                                    <td>
                                        <input type="checkbox"
                                               name="day_hours[<?php echo esc_attr($day_num); ?>][enabled]"
                                               value="1"
                                            <?php checked($enabled); ?>>
                                    </td>
                                    <td>
                                        <input type="time"
                                               name="day_hours[<?php echo esc_attr($day_num); ?>][open]"
                                               value="<?php echo esc_attr($open); ?>">
                                    </td>
                                    <td>
                                        <input type="time"
                                               name="day_hours[<?php echo esc_attr($day_num); ?>][close]"
                                               value="<?php echo esc_attr($close); ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="description">
                            Exemple : Lundi 09:00–17:00, Mardi 09:00–20:00, etc.
                            Décochez un jour pour le marquer comme fermé.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="slot_interval">Intervalle des créneaux (minutes)</label></th>
                    <td>
                        <input type="number" name="slot_interval" id="slot_interval"
                               value="<?php echo esc_attr($s['slot_interval']); ?>"
                               min="5" step="5" style="width:80px;">
                        <p class="description">Exemple : 30 → créneaux 10h, 10h30, 11h, 11h30, etc.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="barbershop_min_delay_minutes">
                            Délai minimum avant un rendez-vous (en minutes)
                        </label>
                    </th>
                    <td>
                        <input
                                type="number"
                                id="barbershop_min_delay_minutes"
                                name="min_delay_minutes"
                                value="<?php echo esc_attr($s['min_delay_minutes']); ?>"
                                min="0"
                                step="5"
                        >
                        <p class="description">
                            Exemple : 180 = n’afficher les créneaux qu’à partir de l’heure actuelle + 3 heures.
                        </p>
                    </td>
                </tr>

            </table>

            <p class="submit">
                <button type="submit" name="barbershop_planning_save" class="button button-primary">Enregistrer</button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Shortcode [barbershop_booking]
 * Étape 1 : choix de la prestation
 * Étape 2 : choix du collaborateur + créneau
 * Étape 3 : récap + infos client + création de la réservation
 */
add_shortcode('barbershop_booking', 'barbershop_booking_shortcode');

function barbershop_booking_shortcode($atts) {
    $atts = shortcode_atts([], $atts, 'barbershop_booking');

    // Déterminer l'étape
    $step          = isset($_REQUEST['bs_step']) ? intval($_REQUEST['bs_step']) : 1;
    $prestation_id = isset($_REQUEST['bs_prestation']) ? intval($_REQUEST['bs_prestation']) : 0;
    $staff_id      = isset($_REQUEST['bs_staff']) ? intval($_REQUEST['bs_staff']) : 0;
    $date          = isset($_REQUEST['bs_date']) ? sanitize_text_field($_REQUEST['bs_date']) : '';
    $time          = isset($_REQUEST['bs_time']) ? sanitize_text_field($_REQUEST['bs_time']) : '';

    // Gestion de la soumission finale (création réservation)
    if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barbershop_booking_confirm'])) {
        return barbershop_booking_handle_submit();
    }

    ob_start();

    echo '<div class="bs-booking-wrapper">';

    if ($step <= 1) {
        barbershop_booking_step1_prestation();
    } elseif ($step === 2 && $prestation_id) {
        barbershop_booking_step2_staff_and_slot($prestation_id, $staff_id, $date, $time);
    } elseif ($step === 3 && $prestation_id && $date && $time) {
        barbershop_booking_step3_summary_and_form($prestation_id, $staff_id, $date, $time);
    } else {
        // fallback → revenir à l'étape 1
        barbershop_booking_step1_prestation();
    }

    echo '</div>';

    return ob_get_clean();
}

/**
 * Étape 1 : liste des prestations groupées par catégories
 */
function barbershop_booking_step1_prestation() {
    $current_url = barbershop_booking_get_current_page_url();
    $staff_id    = isset($_GET['bs_staff']) ? intval($_GET['bs_staff']) : 0;

    // Récupérer les catégories de prestations
    $categories = get_terms([
        'taxonomy'   => 'bs_prestation_category',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
    ?>
    <div class="bs-booking-step bs-booking-step-1">
        <h3 class="bs-booking-title">Choisissez une prestation</h3>

        <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
            <div class="bs-booking-category-grid">
                <?php foreach ($categories as $cat) : ?>

                    <?php
                    // On récupère les prestations de cette catégorie
                    $prestations = new WP_Query([
                        'post_type'      => 'bs_prestation',
                        'posts_per_page' => -1,
                        'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
                        'order'          => 'ASC',
                        'tax_query'      => [
                            [
                                'taxonomy' => 'bs_prestation_category',
                                'field'    => 'term_id',
                                'terms'    => $cat->term_id,
                            ],
                        ],
                    ]);

                    if (!$prestations->have_posts()) {
                        wp_reset_postdata();
                        continue;
                    }

                    // On bufferise la sortie pour pouvoir supprimer la catégorie
                    // si aucune prestation n'est visible pour ce collaborateur.
                    ob_start();
                    $has_visible_prestation = false;
                    ?>
                    <section class="bs-booking-category">
                        <h3 class="bs-booking-category-title">
                            <?php echo esc_html($cat->name); ?>
                        </h3>
                        <?php if (!empty($cat->description)) : ?>
                            <p class="bs-booking-category-desc">
                                <?php echo esc_html($cat->description); ?>
                            </p>
                        <?php endif; ?>

                        <div class="bs-booking-prestation-list">
                            <?php while ($prestations->have_posts()) : $prestations->the_post(); ?>
                                <?php
                                $pid      = get_the_ID();
                                $price    = get_post_meta($pid, '_barbershop_prestation_price', true);
                                $duration = get_post_meta($pid, '_barbershop_prestation_duration', true);

                                // 🔍 Filtre par collaborateur présélectionné
                                if ($staff_id > 0) {
                                    $allowed_staff_ids = barbershop_get_allowed_staff_for_prestation($pid);

                                    // Si la prestation a une liste restreinte ET que ce collaborateur n’y est pas → on saute
                                    if (!empty($allowed_staff_ids) && !in_array($staff_id, $allowed_staff_ids, true)) {
                                        continue;
                                    }
                                }

                                // Si on arrive ici, la prestation est visible
                                $has_visible_prestation = true;

                                $url = add_query_arg([
                                    'bs_step'       => 2,
                                    'bs_prestation' => $pid,
                                    'bs_staff'      => $staff_id,
                                ], $current_url);
                                ?>
                                <article class="bs-booking-prestation-item">
                                    <div class="bs-prestation-text">
                                        <h4 class="bs-prestation-title"><?php the_title(); ?></h4>
                                        <?php if (get_the_content()) : ?>
                                            <p class="bs-prestation-desc">
                                                <?php echo esc_html(wp_strip_all_tags(wp_trim_words(get_the_content(), 15, '…'))); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($duration) : ?>
                                            <p class="bs-prestation-duration"><?php echo esc_html($duration); ?> min</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bs-prestation-action">
                                        <a href="<?php echo esc_url($url); ?>" class="bs-btn bs-btn-primary">
                                            <?php echo esc_html($price ?: 'Choisir'); ?>
                                        </a>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    </section>
                    <?php
                    $category_html = ob_get_clean();
                    wp_reset_postdata();

                    // Si au final aucune prestation n’est visible pour cette catégorie, on ne l’affiche pas
                    if ($has_visible_prestation) {
                        echo $category_html;
                    }
                    ?>

                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p>Aucune prestation n'est encore configurée.</p>
        <?php endif; ?>
    </div>
    <?php
}


function barbershop_booking_get_current_page_url() {
    // 1. Essayer de récupérer l'objet courant (page où le shortcode est affiché)
    $page_id = get_queried_object_id();
    if ($page_id) {
        return get_permalink($page_id);
    }

    // 2. Fallback : reconstruire à partir de la requête HTTP
    $scheme = is_ssl() ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST']  ?? '';
    $uri    = $_SERVER['REQUEST_URI'] ?? '';

    return $scheme . '://' . $host . $uri;
}


/**
 * Étape 2 : choix du collaborateur (ou sans préférence) + créneau
 */
function barbershop_booking_step2_staff_and_slot($prestation_id, $staff_id, $selected_date, $selected_time) {
    $current_url = barbershop_booking_get_current_page_url();
    $page_id = get_queried_object_id();
    $planning    = barbershop_get_planning_settings();

    $prestation = get_post($prestation_id);
    if (!$prestation || $prestation->post_type !== 'bs_prestation') {
        echo '<p>Prestation introuvable.</p>';
        return;
    }

    $duration = intval(get_post_meta($prestation_id, '_barbershop_prestation_duration', true)) ?: 30;

    // Collaborateurs (rôles collaborateur & manager)
    $collaborators = get_users([
        'role__in' => ['collaborateur', 'manager'],
        'orderby'  => 'display_name',
        'order'    => 'ASC',
    ]);

    $allowed_staff_ids = barbershop_get_allowed_staff_for_prestation($prestation_id);
    if (!empty($allowed_staff_ids)) {
        $collaborators = array_filter($collaborators, function($staff_post) use ($allowed_staff_ids) {
            $id = is_object($staff_post) ? $staff_post->ID : (int) $staff_post;
            return in_array($id, $allowed_staff_ids, true);
        });
    }

    // Calcul des jours affichés
    $days_ahead = isset($planning['days_ahead']) ? max(1, intval($planning['days_ahead'])) : 30;
    $interval   = isset($planning['slot_interval']) ? max(5, intval($planning['slot_interval'])) : 30;
    $day_hours  = isset($planning['day_hours']) && is_array($planning['day_hours'])
        ? $planning['day_hours']
        : [];

    // --- Timezone
    $timezone_string = get_option('timezone_string');
    if (function_exists('wp_timezone')) {
        $tz = wp_timezone();
    } else {
        $tz = new DateTimeZone($timezone_string ?: 'UTC');
    }

    // --- Liste des jours ouverts
    $days = [];
    $now = new DateTime('now', $tz);
    $now->setTime(0, 0, 0);

    for ($i = 0; $i < $days_ahead; $i++) {
        $d = clone $now;
        $d->modify("+{$i} days");
        $weekday = $d->format('N'); // 1..7

        $conf = $day_hours[$weekday] ?? null;
        if ($conf && !empty($conf['enabled'])) {
            $days[] = $d;
        }
    }

    // --- Créneaux master : on prend le min "open" et max "close" sur tous les jours ouverts
    $global_open  = null;
    $global_close = null;

    foreach ($day_hours as $day_num => $conf) {
        if (empty($conf['enabled'])) {
            continue;
        }
        $o = $conf['open']  ?? '10:00';
        $c = $conf['close'] ?? '20:00';

        if (!preg_match('/^\d{2}:\d{2}$/', $o) || !preg_match('/^\d{2}:\d{2}$/', $c)) {
            continue;
        }

        if ($global_open === null || $o < $global_open) {
            $global_open = $o;
        }
        if ($global_close === null || $c > $global_close) {
            $global_close = $c;
        }
    }

    // Fallback si jamais rien n'est configuré
    if ($global_open === null || $global_close === null || $global_open >= $global_close) {
        $global_open  = $planning['open_time'] ?? '10:00';
        $global_close = $planning['close_time'] ?? '20:00';
    }

    $time_slots = [];
    $start = DateTime::createFromFormat('H:i', $global_open, $tz);
    $end   = DateTime::createFromFormat('H:i', $global_close, $tz);

    if ($start && $end && $start < $end) {
        $cursor = clone $start;
        while ($cursor < $end) {
            $time_slots[] = $cursor->format('H:i');
            $cursor->modify("+{$interval} minutes");
        }
    }

    // Délai minimum
    $min_delay_minutes = isset($planning['min_delay_minutes'])
        ? max(0, (int) $planning['min_delay_minutes'])
        : 0;

    $now_ts = current_time('timestamp');

    ?>
    <div class="bs-booking-step bs-booking-step-2">
        <h2>Choisissez votre prestataire et le créneau</h2>

        <p class="bs-booking-prestation-selected">
            Prestation sélectionnée :
            <strong><?php echo esc_html(get_the_title($prestation_id)); ?></strong>
            (<?php echo esc_html($duration); ?> min)
        </p>

        <p style="margin-top:1.5rem; margin-bottom:1.5rem;">
            <a href="<?php echo esc_url(add_query_arg(['bs_step' => 1], $current_url)); ?>" class="bs-btn">
                ← Changer de prestation
            </a>
        </p>

        <!-- Choix du collaborateur sous forme de lignes cliquables -->
        <div class="bs-booking-staff-block">
            <p class="bs-booking-staff-title">Choisir un prestataire <?php if (count($collaborators) > 1 ) : ?>(optionnel) <?php endif; ?> </p>
            <div class="bs-booking-staff-options">
                <!-- Sans préférence -->

                <?php if (count($collaborators) > 1 ) : ?>
                <form method="get"
                      action="<?php echo esc_url($current_url); ?>"
                      class="bs-booking-staff-form-inline">
                    <input type="hidden" name="bs_step" value="2">
                    <input type="hidden" name="page_id" value="<?php echo esc_attr($page_id); ?>">
                    <input type="hidden" name="bs_prestation" value="<?php echo esc_attr($prestation_id); ?>">
                    <input type="hidden" name="bs_staff" value="0">
                    <button type="submit"
                            class="bs-staff-option<?php echo empty($staff_id) ? ' bs-staff-option--active' : ''; ?>">
                        Sans préférence
                    </button>
                </form>
                <?php endif; ?>

                <!-- Chaque collaborateur -->
                <?php foreach ($collaborators as $user) : ?>
                    <form method="get"
                          action="<?php echo esc_url($current_url); ?>"
                          class="bs-booking-staff-form-inline">
                        <input type="hidden" name="bs_step" value="2">
                        <input type="hidden" name="page_id" value="<?php echo esc_attr($page_id); ?>">
                        <input type="hidden" name="bs_prestation" value="<?php echo esc_attr($prestation_id); ?>">
                        <input type="hidden" name="bs_staff" value="<?php echo esc_attr($user->ID); ?>">
                        <button type="submit"
                                class="bs-staff-option<?php echo ($staff_id == $user->ID) ? ' bs-staff-option--active' : ''; ?>">
                            <?php echo esc_html($user->first_name); ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Titre choix créneau -->
        <h3 class="bs-booking-slot-title">Choisir un créneau</h3>

        <!-- Grille jours / heures -->
        <div class="bs-booking-grid-wrapper">
            <?php if (empty($days) || empty($time_slots)) : ?>
                <p>Aucun créneau disponible avec les réglages actuels du planning.</p>
            <?php else : ?>
                <div class="bs-booking-grid">
                    <div class="bs-booking-grid-header">
                        <?php

                        $jours = [
                            'Mon' => 'Lundi',
                            'Tue' => 'Mardi',
                            'Wed' => 'Mercredi',
                            'Thu' => 'Jeudi',
                            'Fri' => 'Vendredi',
                            'Sat' => 'Samedi',
                            'Sun' => 'Dimanche'
                        ];

                        $mois = [
                            'Jan' => 'Jan.',
                            'Feb' => 'Fév.',
                            'Mar' => 'Mars',
                            'Apr' => 'Avr.',
                            'May' => 'Mai',
                            'Jun' => 'Juin',
                            'Jul' => 'Juil.',
                            'Aug' => 'Août.',
                            'Sep' => 'Sep.',
                            'Oct' => 'Oct.',
                            'Nov' => 'Nov.',
                            'Dec' => 'Déc.'
                        ];

                        ?>
                        <?php foreach ($days as $day) : ?>
                            <div class="bs-booking-grid-day">
                                <?php
                                $day_short = $day->format('D');
                                $month_short = $day->format('M');
                                ?>
                                <?php echo esc_html($jours[$day_short] ?? $day_short); ?><br>
                                <?php echo esc_html($day->format('d') . ' ' . ($mois[$month_short] ?? $month_short)); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bs-booking-grid-body">
                        <?php foreach ($time_slots as $slot_time) : ?>
                            <div class="bs-booking-grid-row">
                                <?php foreach ($days as $day) : ?>
                                    <?php
                                    $date_str = $day->format('Y-m-d');
                                    $weekday  = $day->format('N'); // 1..7

                                    $conf = $day_hours[$weekday] ?? null;
                                    $day_open  = $conf['open']  ?? $global_open;
                                    $day_close = $conf['close'] ?? $global_close;

                                    // 1) Le créneau fait-il partie de la plage de ce jour ?
                                    $within_day_hours = ($slot_time >= $day_open && $slot_time < $day_close);

                                    // 2) Calcul du timestamp du créneau pour le délai mini
                                    $slot_ts = strtotime($date_str . ' ' . $slot_time);
                                    $too_soon = false;
                                    if ($min_delay_minutes > 0 && $slot_ts !== false) {
                                        $threshold_ts = $now_ts + ($min_delay_minutes * 60);
                                        if ($slot_ts < $threshold_ts) {
                                            $too_soon = true;
                                        }
                                    }

                                    // 3) Disponibilité liée aux réservations
                                    if ($staff_id > 0) {
                                        $slot_available = barbershop_is_slot_available($date_str, $slot_time, $duration, $staff_id);
                                    } elseif (count($collaborators) === 1) {
                                        // Cas "un seul collab"
                                        $only = reset($collaborators);
                                        $auto_staff_id = $only->ID;
                                        $slot_available = barbershop_is_slot_available($date_str, $slot_time, $duration, $auto_staff_id);
                                    } else {
                                        $slot_available = true;
                                    }

                                    // 4) Appliquer délai minimum + plage horaire jour
                                    if ($too_soon || !$within_day_hours) {
                                        $slot_available = false;
                                    }
                                    ?>
                                    <div class="bs-booking-grid-cell">
                                        <?php if ($slot_available) : ?>
                                            <form method="get" action="<?php echo esc_url($current_url); ?>">
                                                <input type="hidden" name="bs_step" value="3">
                                                <input type="hidden" name="page_id" value="<?php echo esc_attr($page_id); ?>">
                                                <input type="hidden" name="bs_prestation" value="<?php echo esc_attr($prestation_id); ?>">
                                                <input type="hidden" name="bs_staff" value="<?php echo esc_attr($staff_id); ?>">
                                                <input type="hidden" name="bs_date" value="<?php echo esc_attr($date_str); ?>">
                                                <input type="hidden" name="bs_time" value="<?php echo esc_attr($slot_time); ?>">
                                                <button type="submit" class="bs-slot-btn">
                                                    <?php echo esc_html(substr($slot_time, 0, 5)); ?>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <span class="bs-slot-unavailable">
                        <?php echo esc_html(substr($slot_time, 0, 5)); ?>
                    </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <p style="margin-top:1.5rem;">
            <a href="<?php echo esc_url(add_query_arg(['bs_step' => 1], $current_url)); ?>" class="bs-btn">
                ← Changer de prestation
            </a>
        </p>
    </div>
    <?php
}



/**
 * Étape 3 : récap + infos client + création de la réservation
 */
function barbershop_booking_step3_summary_and_form($prestation_id, $staff_id, $date, $time) {
    $current_url = barbershop_booking_get_current_page_url();

    $prestation = get_post($prestation_id);
    if (!$prestation || $prestation->post_type !== 'bs_prestation') {
        echo '<p>Prestation introuvable.</p>';
        return;
    }

    $duration = intval(get_post_meta($prestation_id, '_barbershop_prestation_duration', true)) ?: 30;
    $price    = get_post_meta($prestation_id, '_barbershop_prestation_price', true);
    $staff    = $staff_id ? get_user_by('id', $staff_id) : null;

    // Formatage date/heure sécurisé
    $timestamp = strtotime($date . ' ' . $time);
    ?>
    <div class="bs-booking-step bs-booking-step-3">
        <h3>Récapitulatif de votre réservation</h3>

        <div class="bs-booking-summary">
            <p><strong>Prestation :</strong> <?php echo esc_html(get_the_title($prestation_id)); ?></p>
            <?php if ($price) : ?>
                <p><strong>Tarif :</strong> <?php echo esc_html($price); ?></p>
            <?php endif; ?>
            <p><strong>Durée :</strong> <?php echo esc_html($duration); ?> min</p>
            <p><strong>Date :</strong>
                <?php
                if ($timestamp) {
                    echo esc_html(date_i18n(get_option('date_format'), $timestamp));
                } else {
                    echo esc_html($date);
                }
                ?>
            </p>
            <p><strong>Heure :</strong> <?php echo esc_html($time); ?></p>
            <p><strong>Prestation par :</strong>
                <?php
                if ($staff) {
                    echo esc_html($staff->first_name);
                } else {
                    echo 'Sans préférence';
                }
                ?>
            </p>
        </div>

        <h3>Vos informations</h3>

        <form method="post" class="bs-booking-client-form">
            <?php wp_nonce_field('barbershop_booking_submit', 'barbershop_booking_nonce'); ?>

            <input type="hidden" name="bs_step" value="3">
            <input type="hidden" name="bs_prestation" value="<?php echo esc_attr($prestation_id); ?>">
            <input type="hidden" name="bs_staff" value="<?php echo esc_attr($staff_id); ?>">
            <input type="hidden" name="bs_date" value="<?php echo esc_attr($date); ?>">
            <input type="hidden" name="bs_time" value="<?php echo esc_attr($time); ?>">
            <input type="hidden" name="bs_duration" value="<?php echo esc_attr($duration); ?>">

            <p>
                <label>Nom complet<br>
                    <input type="text" name="bs_client_name" required>
                </label>
            </p>
            <p>
                <label>Email<br>
                    <input type="email" name="bs_client_email" required>
                </label>
            </p>
            <p>
                <label>Téléphone<br>
                    <input type="text" name="bs_client_phone">
                </label>
            </p>
            <p>
                <label>Commentaire / précision (optionnel)<br>
                    <textarea name="bs_client_note" rows="3"></textarea>
                </label>
            </p>

<!--            <p>-->
<!--                <label>-->
<!--                    <input type="checkbox" name="bs_create_account" value="1">-->
<!--                    Créer un compte client avec cet e-mail-->
<!--                </label>-->
<!--            </p>-->

            <p>
                <button type="submit" name="barbershop_booking_confirm" class="bs-btn bs-btn-primary">
                    Confirmer la demande de rendez-vous
                </button>

                <a href="<?php echo esc_url(add_query_arg([
                    'bs_step'      => 2,
                    'bs_prestation'=> $prestation_id,
                    'bs_staff'     => $staff_id,
                ], $current_url)); ?>" class="bs-btn">
                    Ou changer de créneau
                </a>

            </p>
        </form>

    </div>
    <?php
}


/**
 * Traitement de la soumission finale : création de la réservation
 */
function barbershop_booking_handle_submit() {
    if (!isset($_POST['barbershop_booking_nonce']) || !wp_verify_nonce($_POST['barbershop_booking_nonce'], 'barbershop_booking_submit')) {
        return '<p>Erreur de sécurité. Merci de réessayer.</p>';
    }

    $prestation_id = intval($_POST['bs_prestation'] ?? 0);
    $staff_id      = intval($_POST['bs_staff'] ?? 0);
    $date          = sanitize_text_field($_POST['bs_date'] ?? '');
    $time          = sanitize_text_field($_POST['bs_time'] ?? '');
    $duration      = intval($_POST['bs_duration'] ?? 0);

    if ($staff_id > 0) {
        $slot_still_available = barbershop_is_slot_available($date, $time, $duration, $staff_id);

        if (!$slot_still_available) {
            return '<p class="bs-booking-step-confirm"> 
Le créneau choisi est déjà pris pour ce prestataire. Veuillez svp choisir un autre créneau. </p><br>
<a href="'
                . esc_url(add_query_arg(['bs_step' => 2, 'bs_prestation' => $prestation_id, 'bs_staff' => $staff_id,], barbershop_booking_get_current_page_url() ))
                . '" class="bs-btn">Changer de créneau</a>';
        }
    }

    $client_name   = sanitize_text_field($_POST['bs_client_name'] ?? '');
    $client_email  = sanitize_email($_POST['bs_client_email'] ?? '');
    $client_phone  = sanitize_text_field($_POST['bs_client_phone'] ?? '');
    $client_note   = sanitize_textarea_field($_POST['bs_client_note'] ?? '');
    $client_msg    = isset($_POST['bs_client_message']) ? sanitize_textarea_field($_POST['bs_client_message']) : '';
    $create_account = !empty($_POST['bs_create_account']);

    if (!$prestation_id || !$date || !$time || !$client_name || !$client_email) {
        return '<p class="bs-booking-step-confirm">Merci de remplir tous les champs obligatoires.</p>';
    }

    // Optionnel : création de compte client
    $client_user_id = 0;
    if ($create_account && !email_exists($client_email)) {
        $random_password = wp_generate_password(12, false);
        $user_id = wp_create_user($client_email, $random_password, $client_email);
        if (!is_wp_error($user_id)) {
            $client_user_id = $user_id;
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => $client_name,
            ]);
            // TODO : envoyer un e-mail avec les identifiants
        }
    }

    // Création de la réservation
    $prestation_title = get_the_title($prestation_id);
    $post_title = sprintf(
        'Réservation - %s%s%s',
        $prestation_title,
        $date,
        $time
    );

    $reservation_id = wp_insert_post([
        'post_type'   => 'bs_reservation',
        'post_title'  => $post_title,
        'post_status' => 'publish',
    ]);

    if (is_wp_error($reservation_id)) {
        return '<p>Une erreur est survenue lors de l\'enregistrement de votre demande. Merci de réessayer.</p>';
    }

    update_post_meta($reservation_id, '_barbershop_reservation_prestation_id', $prestation_id);
    update_post_meta($reservation_id, '_barbershop_reservation_collaborator_id', $staff_id);
    update_post_meta($reservation_id, '_barbershop_reservation_date', $date);
    update_post_meta($reservation_id, '_barbershop_reservation_time', $time);
    update_post_meta($reservation_id, '_barbershop_reservation_duration', $duration);
    update_post_meta($reservation_id, '_barbershop_reservation_client_name', $client_name);
    update_post_meta($reservation_id, '_barbershop_reservation_client_email', $client_email);
    update_post_meta($reservation_id, '_barbershop_reservation_client_phone', $client_phone);
    update_post_meta($reservation_id, '_barbershop_reservation_client_note', $client_note);
    if ($client_user_id) {
        update_post_meta($reservation_id, '_barbershop_reservation_client_user_id', $client_user_id);
    }

    barbershop_send_booking_emails($reservation_id, [
        'prestation_id'  => $prestation_id,
        'staff_id'       => $staff_id,
        'date'           => $date,
        'time'           => $time,
        'duration'       => $duration,
        'client_name'    => $client_name,
        'client_email'   => $client_email,
        'client_message' => $client_msg,
    ]);

    ob_start();
    ?>
    <div class="bs-booking-step bs-booking-step-confirm">
        <h3>Merci pour votre reservation !</h3>
        <p>Votre demande de rendez-vous a bien été enregistrée.
            <br>
            Vous allez recevoir un email de confirmation. Il pourrait s'être glissé dans le dossier spam, veuillez y jeter un coup d'oeil si besoin.
        </p>
        <p>
            L'équipe PlaisirBarber90
        </p>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Envoie les e-mails de confirmation (admin + client) après une réservation.
 *
 * @param int   $reservation_id  ID du post bs_reservation
 * @param array $data            Données utiles (prestation, staff, date, time, client, etc.)
 */
function barbershop_send_booking_emails($reservation_id, array $data = []) {
    if (!$reservation_id || get_post_type($reservation_id) !== 'bs_reservation') {
        return;
    }

    // Données brutes
    $prestation_id   = isset($data['prestation_id']) ? (int) $data['prestation_id'] : 0;
    $staff_id        = isset($data['staff_id']) ? (int) $data['staff_id'] : 0;
    $date            = $data['date'] ?? get_post_meta($reservation_id, '_barbershop_reservation_date', true);
    $time            = $data['time'] ?? get_post_meta($reservation_id, '_barbershop_reservation_time', true);
    $duration        = isset($data['duration']) ? (int) $data['duration'] : (int) get_post_meta($reservation_id, '_barbershop_reservation_duration', true);
    $client_name     = $data['client_name'] ?? get_post_meta($reservation_id, '_barbershop_reservation_client_name', true);
    $client_email    = $data['client_email'] ?? get_post_meta($reservation_id, '_barbershop_reservation_client_email', true);
    $client_message  = $data['client_message'] ?? get_post_meta($reservation_id, '_barbershop_reservation_client_message', true);

    $prestation_title = $prestation_id ? get_the_title($prestation_id) : '';
    $staff_name       = $staff_id ? get_the_title($staff_id) : __('Sans préférence', 'barbershop');

    // Formatage date/heure pour l'email
    try {
        $dt = new DateTime($date . ' ' . $time);
        // ex: Lundi 02 novembre 2025 à 14:30
        $date_formatted = date_i18n('l d F Y', $dt->getTimestamp());
        $time_formatted = date_i18n('H:i', $dt->getTimestamp());
    } catch (Exception $e) {
        $date_formatted = $date;
        $time_formatted = $time;
    }

    $duration_txt = $duration > 0 ? $duration . ' min' : '';

    // Email du salon (option personnalisable sinon admin_email)
    $admin_email = get_option('barbershop_booking_email');
    if (empty($admin_email) || !is_email($admin_email)) {
        $admin_email = get_option('admin_email');
    }

    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

    // --------------------------------
    // 1) Email à l’ADMIN
    // --------------------------------
    $subject_admin = sprintf(
        __('[%s] Nouvelle réservation de rendez-vous', 'barbershop'),
        $site_name
    );

    $message_admin  = '<p>Une nouvelle réservation a été confirmée.</p>';
    $message_admin .= '<p><strong>Détails du rendez-vous :</strong></p>';
    $message_admin .= '<ul>';
    if ($prestation_title) {
        $message_admin .= '<li><strong>Prestation :</strong> ' . esc_html($prestation_title) . '</li>';
    }
    $message_admin .= '<li><strong>Avec :</strong> ' . esc_html($staff_name) . '</li>';
    $message_admin .= '<li><strong>Date :</strong> ' . esc_html($date_formatted) . '</li>';
    $message_admin .= '<li><strong>Heure :</strong> ' . esc_html($time_formatted) . '</li>';
    if ($duration_txt) {
        $message_admin .= '<li><strong>Durée :</strong> ' . esc_html($duration_txt) . '</li>';
    }
    $message_admin .= '</ul>';

    $message_admin .= '<p><strong>Client :</strong></p>';
    $message_admin .= '<ul>';
    if ($client_name) {
        $message_admin .= '<li><strong>Nom :</strong> ' . esc_html($client_name) . '</li>';
    }
    if ($client_email) {
        $message_admin .= '<li><strong>Email :</strong> ' . esc_html($client_email) . '</li>';
    }
    if ($client_message) {
        $message_admin .= '<li><strong>Message :</strong><br>' . nl2br(esc_html($client_message)) . '</li>';
    }
    $message_admin .= '</ul>';

    $message_admin .= '<p><em>ID de la réservation :</em> ' . (int) $reservation_id . '</p>';

    $headers_admin = [
        'Content-Type: text/html; charset=UTF-8',
    ];

    // On met le client en Reply-To si possible
    if (!empty($client_email) && is_email($client_email)) {
        $headers_admin[] = 'Reply-To: ' . $client_name . ' <' . $client_email . '>';
    }

    wp_mail($admin_email, $subject_admin, $message_admin, $headers_admin);

    // --------------------------------
    // 2) Email au CLIENT
    // --------------------------------
    if (!empty($client_email) && is_email($client_email)) {
        $subject_client = sprintf(
            __('Confirmation de votre rendez-vous - %s', 'barbershop'),
            $site_name
        );

        $message_client  = '<p>Bonjour ' . esc_html($client_name) . ',</p>';
        $message_client .= '<p>Merci pour votre réservation. Voici un récapitulatif de votre rendez-vous :</p>';
        $message_client .= '<ul>';
        if ($prestation_title) {
            $message_client .= '<li><strong>Prestation :</strong> ' . esc_html($prestation_title) . '</li>';
        }
        $message_client .= '<li><strong>Rendez-vous avec :</strong> ' . esc_html($staff_name) . '</li>';
        $message_client .= '<li><strong>Date :</strong> ' . esc_html($date_formatted) . '</li>';
        $message_client .= '<li><strong>Heure :</strong> ' . esc_html($time_formatted) . '</li>';
        if ($duration_txt) {
            $message_client .= '<li><strong>Durée théorique :</strong> ' . esc_html($duration_txt) . '</li>';
        }
        $message_client .= '</ul>';

        if ($client_message) {
            $message_client .= '<p><strong>Votre message :</strong><br>' . nl2br(esc_html($client_message)) . '</p>';
        }

        $message_client .= '<p>Si vous souhaitez modifier ou annuler ce rendez-vous, merci de nous contacter.</p>';
        $message_client .= '<p>Cordialement,<br>' . esc_html($site_name) . '</p>';

        $headers_client = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>',
        ];

        wp_mail($client_email, $subject_client, $message_client, $headers_client);
    }
}


/**
 * Colonnes personnalisées pour le post type "barbershop_reservation"
 */
add_filter('manage_edit-bs_reservation_columns', 'barbershop_reservation_edit_columns');
function barbershop_reservation_edit_columns($columns) {
    $new = [];

    foreach ($columns as $key => $label) {
        // On garde la colonne Titre, puis on injecte nos colonnes juste après
        if ($key === 'title') {
            $new[$key] = $label;

            $new['barbershop_prestation'] = 'Prestation';
            $new['barbershop_date']       = 'Date';
            $new['barbershop_time']       = 'Créneau';
            $new['bs_staff']      = 'Collaborateur';
        } else {
            // On garde les autres colonnes par défaut (date, etc.)
            $new[$key] = $label;
        }
    }

    return $new;
}

/**
 * Contenu des colonnes personnalisées
 */
add_action('manage_bs_reservation_posts_custom_column', 'barbershop_reservation_custom_column', 10, 2);
function barbershop_reservation_custom_column($column, $post_id) {
    switch ($column) {
        case 'barbershop_prestation':
            // ID de la prestation (essaie plusieurs clés au cas où)
            $prestation_id =
                get_post_meta($post_id, '_barbershop_reservation_prestation_id', true)
                    ?: get_post_meta($post_id, 'bs_prestation', true)
                    ?: get_post_meta($post_id, 'prestation_id', true);

            if ($prestation_id) {
                $title = get_the_title($prestation_id);
                echo $title ? esc_html($title) : '<em>Inconnue</em>';
            } else {
                echo '<em>Non renseignée</em>';
            }
            break;

        case 'barbershop_date':
            $date =
                get_post_meta($post_id, '_barbershop_reservation_date', true)
                    ?: get_post_meta($post_id, 'bs_date', true)
                    ?: get_post_meta($post_id, 'date', true);

            if ($date) {
                $ts = strtotime($date);
                echo esc_html($ts ? date_i18n(get_option('date_format'), $ts) : $date);
            } else {
                echo '<em>—</em>';
            }
            break;

        case 'barbershop_time':
            $time =
                get_post_meta($post_id, '_barbershop_reservation_time', true)
                    ?: get_post_meta($post_id, 'bs_time', true)
                    ?: get_post_meta($post_id, 'time', true);

            echo $time ? esc_html($time) : '<em>—</em>';
            break;

        case 'bs_staff':
            $staff_id =
                get_post_meta($post_id, '_barbershop_reservation_collaborator_id', true)
                    ?: get_post_meta($post_id, 'bs_staff', true)
                    ?: get_post_meta($post_id, 'staff_id', true);

            if ($staff_id) {
                $user = get_user_by('id', $staff_id);
                echo $user ? '<strong>'. esc_html($user->first_name) .'</strong>' : '<em>Introuvable</em>';
            } else {
                echo '<em>Sans préférence</em>';
            }
            break;
    }
}

add_action('admin_notices', 'barbershop_reservation_conflict_admin_notice');
function barbershop_reservation_conflict_admin_notice() {
    global $post;

    if (!isset($post) || $post->post_type !== 'bs_reservation') {
        return;
    }

    if (get_transient('barbershop_reservation_conflict_' . $post->ID)) {
        delete_transient('barbershop_reservation_conflict_' . $post->ID);
        ?>
        <div class="notice notice-error">
            <p>
                Impossible d'enregistrer cette réservation :
                le prestataire a déjà un rendez-vous sur ce créneau
                (même date, plage horaire qui se chevauche).
            </p>
        </div>
        <?php
    }
}

function barbershop_current_user_has_role($role) {
    if (!is_user_logged_in()) {
        return false;
    }
    $user = wp_get_current_user();
    return in_array($role, (array) $user->roles, true);
}

add_action('admin_menu', 'barbershop_restrict_admin_menu', 999);
function barbershop_restrict_admin_menu() {

    // Collaborateur : interface ultra simplifiée
    if (barbershop_current_user_has_role('collaborateur')) {

        // On cache quasiment tout
        remove_menu_page('index.php');                  // Tableau de bord
        remove_menu_page('edit.php');                   // Articles
        remove_menu_page('upload.php');                 // Médias
        remove_menu_page('edit.php?post_type=page');    // Pages
        remove_menu_page('edit-comments.php');          // Commentaires
        remove_menu_page('themes.php');                 // Apparence
        remove_menu_page('plugins.php');                // Extensions
        remove_menu_page('tools.php');                  // Outils
        remove_menu_page('options-general.php');        // Réglages
        remove_menu_page('edit.php?post_type=bs_prestation'); // Prestations

        // On enlève le menu "Utilisateurs" (mais le profil reste accessible via /profile.php)
        remove_menu_page('users.php');

        // Le menu "Réservations" (bs_reservation) reste visible grâce aux caps
    }
}

add_action('admin_init', 'barbershop_restrict_admin_screens');
function barbershop_restrict_admin_screens() {
    if (!barbershop_current_user_has_role('collaborateur')) {
        return;
    }

    // Autoriser seulement :
    // - la liste des réservations
    // - l'édition/création de réservation
    // - la page "Profil"
    $screen = get_current_screen();
    if (!$screen) {
        return;
    }

    $allowed_screens = [
        'profile',                       // profil utilisateur
        'profile-network',               // (multisite éventuel)
        'edit-bs_reservation',           // liste des réservations
        'bs_reservation',                // écran d'édition d'une réservation
    ];

    if (!in_array($screen->id, $allowed_screens, true)) {
        wp_redirect(admin_url('edit.php?post_type=bs_reservation'));
        exit;
    }
}

add_action('admin_bar_menu', 'barbershop_customize_admin_bar', 999);
function barbershop_customize_admin_bar($wp_admin_bar) {
    if (!barbershop_current_user_has_role('collaborateur')) {
        return;
    }

    // On enlève quelques noeuds "global"
    $wp_admin_bar->remove_node('new-content');   // + Nouveau
    $wp_admin_bar->remove_node('comments');      // Commentaires
    $wp_admin_bar->remove_node('updates');       // Mises à jour
    // Tu peux en enlever d'autres si tu veux
}

function barbershop_is_booking_context() {
    // 1) Si on a des paramètres du booking dans l’URL
    if (!empty($_GET['bs_step']) || !empty($_GET['bs_prestation']) || !empty($_GET['bs_staff'])) {
        return true;
    }

    // 2) Si la page courante contient le shortcode [barbershop_booking]
    if (is_singular()) {
        global $post;
        if ($post instanceof WP_Post && has_shortcode($post->post_content ?? '', 'barbershop_booking')) {
            return true;
        }
    }

    return false;
}

add_filter('nav_menu_css_class', 'barbershop_mark_rdv_menu_active', 10, 3);
function barbershop_mark_rdv_menu_active($classes, $item, $args) {

    // On ne fait ça que si on est dans le contexte réservation
    if (!barbershop_is_booking_context()) {
        return $classes;
    }

    // On ne touche qu’aux items marqués avec la classe CSS "barbershop-rdv-link"
    if (in_array('barbershop-rdv-link', (array) $classes, true)) {

        // On ajoute les mêmes classes que WordPress utilise pour l’item courant
        if (!in_array('current-menu-item', $classes, true)) {
            $classes[] = 'current-menu-item';
        }
        if (!in_array('current_page_item', $classes, true)) {
            $classes[] = 'current_page_item';
        }
    }

    return $classes;
}




