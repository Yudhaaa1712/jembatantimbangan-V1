<?php
// Material Functions - Database-based Material Management

/**
 * Get all materials from database
 */
function get_all_materials() {
    global $conn;

    $materials = [];
    $query = "SELECT * FROM materials WHERE status = 'active' ORDER BY nama_material";
    $result = mysqli_query($conn, $query);

    while ($row = mysqli_fetch_assoc($result)) {
        $materials[$row['kode_material']] = [
            'kode' => $row['kode_material'],
            'nama' => $row['nama_material'],
            'deskripsi' => $row['deskripsi'],
            'icon' => $row['icon'],
            'satuan' => $row['satuan']
        ];
    }

    return $materials;
}

/**
 * Get material by code
 */
function get_material($kode) {
    $materials = get_all_materials();
    return $materials[$kode] ?? null;
}

/**
 * Get material name by code
 */
function get_material_name($kode) {
    $material = get_material($kode);
    return $material ? $material['nama'] : $kode;
}

/**
 * Get material options for dropdown
 */
function get_material_options($selected = '') {
    $materials = get_all_materials();
    $options = '<option value="">Pilih Material</option>';

    foreach ($materials as $kode => $data) {
        $selected_attr = ($selected == $kode) ? 'selected' : '';
        $options .= "<option value=\"{$kode}\" {$selected_attr}>{$data['nama']}</option>";
    }

    return $options;
}

/**
 * Convert material code to display name (for JavaScript usage)
 */
function get_material_js_mapping() {
    $materials = get_all_materials();
    $mapping = [];

    foreach ($materials as $kode => $data) {
        $mapping[$kode] = $data['nama'];
    }

    return json_encode($mapping);
}
?>