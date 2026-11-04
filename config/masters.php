<?php

/**
 * Master Data (dropdown values) managed by Super Admin from the side menu.
 * key => label, group, optional parent type (dependent dropdown), pincode (tehsil/town carries a pincode that auto-fills on forms).
 */
return [
    // 'fixed' => true: system value list (not editable), shown only so the menu matches the live site.
    'groups' => ['Location', 'Family & Member', 'Committee', 'Mangal Parinay', 'Job Portal', 'Trust', 'Reports & Notification'],
    'types' => [
        // Location (Anchal -> Pradesh -> State -> District -> Tehsil/Town). Anchal is optional for a Pradesh (older Pradesh values may have none).
        'anchal'   => ['label' => 'ABMM Anchal',      'group' => 'Location'],
        'pradesh'  => ['label' => 'ABMM Pradesh',     'group' => 'Location', 'parent' => 'anchal', 'parent_optional' => true],
        'state'    => ['label' => 'State',            'group' => 'Location', 'parent' => 'pradesh'],
        'district' => ['label' => 'District',         'group' => 'Location', 'parent' => 'state'],
        'tehsil'   => ['label' => 'Tehsil / Town',    'group' => 'Location', 'parent' => 'district', 'pincode' => true],
        'country'  => ['label' => 'Country (+ phone code)', 'group' => 'Location', 'dial' => true],
        'city'     => ['label' => 'City (all India)', 'group' => 'Location'],
        // Family & member
        'caste'          => ['label' => 'Caste',                'group' => 'Family & Member'],
        'gotra'          => ['label' => 'Gotra',                'group' => 'Family & Member', 'parent' => 'caste'],
        'surname'        => ['label' => 'Surname (Maheshwari)', 'group' => 'Family & Member'],
        'resident_type'  => ['label' => 'Resident Type',        'group' => 'Family & Member'],
        'marital_status' => ['label' => 'Marital Status',       'group' => 'Family & Member'],
        'education'      => ['label' => 'Qualification',        'group' => 'Family & Member'],
        'occupation'     => ['label' => 'Occupation',           'group' => 'Family & Member'],
        'profession'     => ['label' => 'Profession',           'group' => 'Family & Member'],
        'business_type'  => ['label' => 'Business Type',        'group' => 'Family & Member'],
        'blood_group'    => ['label' => 'Blood Group',          'group' => 'Family & Member'],
        'relation'       => ['label' => 'Relation (Member)',    'group' => 'Family & Member'],
        'gender'         => ['label' => 'Gender',               'group' => 'Family & Member'],
        'pradesh_availability' => ['label' => 'ABMM Pradesh Availability (Yes / No)', 'group' => 'Family & Member', 'fixed' => true],  // fixed Yes/No filter: view only
        'income'         => ['label' => 'Income Range',         'group' => 'Family & Member'],
        // Committee
        'designation' => ['label' => 'Designation', 'group' => 'Committee'],
        // Mangal Parinay
        'parinay_qualification'   => ['label' => 'Parinay Qualification',   'group' => 'Mangal Parinay'],
        'parinay_degree'          => ['label' => 'Parinay Degree',          'group' => 'Mangal Parinay'],
        'parinay_occupation_type' => ['label' => 'Parinay Occupation Type', 'group' => 'Mangal Parinay'],
        'parinay_occupation'      => ['label' => 'Parinay Occupation',      'group' => 'Mangal Parinay'],
        'parinay_status'          => ['label' => 'Parinay Status',          'group' => 'Mangal Parinay'],
        'parinay_marital'         => ['label' => 'Parinay Marital Status',  'group' => 'Mangal Parinay'],
        'diet'                    => ['label' => 'Diet (Veg / Non-Veg ...)', 'group' => 'Mangal Parinay'],
        'salary_range'            => ['label' => 'Income Range - yearly (e.g. 2-5 Lakh)', 'group' => 'Mangal Parinay', 'help' => 'The name is the range: "Below 2 Lakh", "2-5 Lakh", "50 Lakh and above". The app and admin income filters are built from it.'],
        // Job Portal
        'job_category'            => ['label' => 'Job Category',            'group' => 'Job Portal'],
        'job_salary_range'        => ['label' => 'Job Salary Range - monthly (e.g. 10k-20k)', 'group' => 'Job Portal', 'help' => 'The name is the range: "Below 10k", "10k-20k", "60k-1 Lakh", "1 Lakh and above".'],
        // Trust form: Category -> Name of Trust (dependent dropdown). Gender / Marital Status reuse the Family & Member masters.
        'trust_category' => ['label' => 'Trust Category',  'group' => 'Trust'],
        'trust_name'     => ['label' => 'Name of Trust',   'group' => 'Trust', 'parent' => 'trust_category'],
        // Reports & notification
        'age_group'       => ['label' => 'Age Group',                  'group' => 'Reports & Notification'],
        'archive_type'    => ['label' => 'Archive Type (Hanging Data)', 'group' => 'Reports & Notification'],
        'notify_audience' => ['label' => 'Notification: Send To',      'group' => 'Reports & Notification'],
        'notify_time'     => ['label' => 'Meeting Time Slots',         'group' => 'Reports & Notification'],
    ],
];
