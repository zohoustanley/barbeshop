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
            'barbershop_read_reservations' => true,
        ],
        'manager' => [
            'read'                               => true,
            'barbershop_read_reservations'       => true,
            'barbershop_manage_all_reservations' => true,
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

    /**
     * TODO (planning) :
     * - calculer datetime début/fin (timestamp) à partir de date + time + durée
     * - vérifier qu'il n’existe pas déjà une réservation pour ce collaborateur
     *   sur un créneau qui se chevauche
     * - vérifier que la date/heure tombe dans ses plages de dispo
     */
}

/**
 * Réglages du planning Barbershop
 * - horaires d'ouverture
 * - intervalle des créneaux
 * - nombre de jours affichés
 */

function barbershop_get_planning_defaults() {
    return [
        'days_ahead'    => 30,        // nombre de jours à afficher
        'open_time'     => '10:00',   // heure d'ouverture (HH:MM)
        'close_time'    => '20:00',   // heure de fermeture (HH:MM)
        'slot_interval' => 30,        // minutes (15, 30, 60, etc.)
        'open_days'     => ['1','2','3','4','5','6'], // 1=lundi ... 7=dimanche (PHP date('N'))
    ];
}

function barbershop_get_planning_settings() {
    $defaults = barbershop_get_planning_defaults();
    $settings  = get_option('barbershop_planning', []);
    return wp_parse_args($settings, $defaults);
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
    // Pour l’instant réservé aux admins (manage_options).
    // On pourra plus tard utiliser une capability custom pour les managers.
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

        $settings                    = barbershop_get_planning_settings();
        $settings['days_ahead']      = max(1, intval($_POST['days_ahead'] ?? 30));
        $settings['open_time']       = sanitize_text_field($_POST['open_time'] ?? '10:00');
        $settings['close_time']      = sanitize_text_field($_POST['close_time'] ?? '20:00');
        $settings['slot_interval']   = max(5, intval($_POST['slot_interval'] ?? 30));

        $open_days = isset($_POST['open_days']) && is_array($_POST['open_days']) ? array_map('sanitize_text_field', $_POST['open_days']) : [];
        $settings['open_days'] = $open_days ?: ['1','2','3','4','5','6'];

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
                    <th scope="row">Jours d'ouverture</th>
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
                        foreach ($days_labels as $value => $label) :
                            ?>
                            <label style="display:inline-block;margin-right:10px;">
                                <input type="checkbox" name="open_days[]" value="<?php echo esc_attr($value); ?>"
                                    <?php checked(in_array($value, (array) $s['open_days'], true)); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">Jours où le salon est ouvert pour les réservations.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="open_time">Heure d'ouverture</label></th>
                    <td>
                        <input type="time" name="open_time" id="open_time"
                               value="<?php echo esc_attr($s['open_time']); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="close_time">Heure de fermeture</label></th>
                    <td>
                        <input type="time" name="close_time" id="close_time"
                               value="<?php echo esc_attr($s['close_time']); ?>">
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
    $staff_id = isset($_GET['bs_staff']) ? intval($_GET['bs_staff']) : 0;

    // Récupérer les catégories de prestations
    $categories = get_terms([
        'taxonomy'   => 'bs_prestation_category',  // adapte si ton slug diffère
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

                    // ✅ Ne pas afficher la catégorie si aucun résultat
                    if (!$prestations->have_posts()) {
                        wp_reset_postdata();
                        continue;
                    }
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
                                $url      = add_query_arg([
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

                        <?php wp_reset_postdata(); ?>
                    </section>

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
    $days_ahead  = isset($planning['days_ahead']) ? max(1, intval($planning['days_ahead'])) : 30;
    $open_days   = isset($planning['open_days']) ? (array) $planning['open_days'] : ['1','2','3','4','5','6'];
    $open_time   = isset($planning['open_time']) ? $planning['open_time'] : '10:00';
    $close_time  = isset($planning['close_time']) ? $planning['close_time'] : '20:00';
    $interval    = isset($planning['slot_interval']) ? max(5, intval($planning['slot_interval'])) : 30;

    // Générer la liste des jours (timestamps à minuit local)
    $days = [];

    $timezone_string = get_option('timezone_string');
    if (function_exists('wp_timezone')) {
        $tz = wp_timezone();
    } else {
        $tz = new DateTimeZone($timezone_string ?: 'UTC');
    }

    $now = new DateTime('now', $tz);
    $now->setTime(0, 0, 0);

    for ($i = 0; $i < $days_ahead; $i++) {
        $d = clone $now;
        $d->modify("+{$i} days");
        $weekday = $d->format('N'); // 1=lundi ... 7=dimanche
        if (in_array($weekday, $open_days, true)) {
            $days[] = $d;
        }
    }

    // Générer les créneaux horaires
    $time_slots = [];
    if ($open_time && $close_time) {
        $start = DateTime::createFromFormat('H:i', $open_time, $tz);
        $end   = DateTime::createFromFormat('H:i', $close_time, $tz);

        if ($start && $end && $start < $end) {
            $cursor = clone $start;
            while ($cursor < $end) {
                $time_slots[] = $cursor->format('H:i');
                $cursor->modify("+{$interval} minutes");
            }
        }
    }

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
            <p class="bs-booking-staff-title">Choisir un collaborateur <?php if (count($collaborators) > 1 ) : ?>(optionnel) <?php endif; ?> </p>
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
                        <?php foreach ($days as $day) : ?>
                            <div class="bs-booking-grid-day">
                                <?php echo esc_html($day->format('d/m')); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bs-booking-grid-body">
                        <?php foreach ($time_slots as $slot_time) : ?>
                            <div class="bs-booking-grid-row">
                                <?php foreach ($days as $day) : ?>
                                    <?php
                                    $date_str       = $day->format('Y-m-d');

                                    if ($staff_id > 0) {
                                        // Contrôle strict : on vérifie que ce collaborateur n'est pas déjà pris
                                        $slot_available = barbershop_is_slot_available($date_str, $slot_time, $duration, $staff_id);
                                    }
                                    else if (count($collaborators) === 1) {
                                        $staff_id = array_shift($collaborators)->ID;
                                        // Contrôle strict : on vérifie s'il n'y a qu'un seul collaborateur mais non selectionné
                                        $slot_available = barbershop_is_slot_available($date_str, $slot_time, $duration, $staff_id);
                                    }

                                    else {
                                        // "Sans préférence" : on ne bloque pas côté affichage
                                        // (la vérification se fera surtout au moment de la soumission finale)
                                        $slot_available = true;
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

            <p>
                <label>
                    <input type="checkbox" name="bs_create_account" value="1">
                    Créer un compte client avec cet e-mail
                </label>
            </p>

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
            return '<p class="bs-booking-step-confirm"> Le créneau choisi est déjà réservé pour ce collaborateur. Merci de choisir un autre créneau. </p><br><a href="'
                . esc_url(add_query_arg(['bs_step' => 2, 'bs_prestation' => $prestation_id, 'bs_staff' => $staff_id,], barbershop_booking_get_current_page_url() ))
                . '" class="bs-btn">Changer de créneau</a>';
        }
    }

    $client_name   = sanitize_text_field($_POST['bs_client_name'] ?? '');
    $client_email  = sanitize_email($_POST['bs_client_email'] ?? '');
    $client_phone  = sanitize_text_field($_POST['bs_client_phone'] ?? '');
    $client_note   = sanitize_textarea_field($_POST['bs_client_note'] ?? '');
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

    // TODO : envoyer un e-mail de confirmation au client et au salon

    ob_start();
    ?>
    <div class="bs-booking-step bs-booking-step-confirm">
        <h3>Votre demande de rendez-vous a bien été enregistrée</h3>
        <h4>Vous recevrez une confirmation par e-mail une fois que le salon aura validé votre réservation.</h4>
    </div>
    <?php
    return ob_get_clean();
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
                le collaborateur a déjà un rendez-vous sur ce créneau
                (même date, plage horaire qui se chevauche).
            </p>
        </div>
        <?php
    }
}



