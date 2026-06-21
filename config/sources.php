<?php

declare(strict_types=1);

function getSourceDefinitions(): array
{
    return [
        'eTender' => [
            'label' => 'eTender',
            'table' => 'egp_tender_records',
            'page_url' => 'https://www.eprocure.gov.bd/resources/common/AllTenders.jsp?h=t',
            'endpoint' => 'https://www.eprocure.gov.bd/TenderDetailsServlet',
            'default_page_size' => 10,
            'preview_columns' => [
                ['key' => 'tender_id', 'label' => 'Tender ID'],
                ['key' => 'reference_no', 'label' => 'Reference'],
                ['key' => 'tender_title', 'label' => 'Title'],
                ['key' => 'procuring_entity', 'label' => 'Procuring Entity'],
                ['key' => 'procurement_method', 'label' => 'Method'],
                ['key' => 'publishing_raw', 'label' => 'Published'],
                ['key' => 'closing_raw', 'label' => 'Closing'],
            ],
        ],
        'APP' => [
            'label' => 'APP',
            'table' => 'egp_app_records',
            'page_url' => 'https://www.eprocure.gov.bd/resources/common/AdvAPPSearch.jsp',
            'endpoint' => 'https://www.eprocure.gov.bd/SearchServlet',
            'default_page_size' => 10,
            'preview_columns' => [
                ['key' => 'app_id', 'label' => 'APP ID'],
                ['key' => 'app_code', 'label' => 'APP Code'],
                ['key' => 'project_name', 'label' => 'Project'],
                ['key' => 'procuring_entity', 'label' => 'Procuring Entity'],
                ['key' => 'district', 'label' => 'District'],
                ['key' => 'estimated_cost_raw', 'label' => 'Estimated Cost'],
                ['key' => 'procurement_method', 'label' => 'Method'],
            ],
        ],
        'eContract' => [
            'label' => 'eContract',
            'table' => 'egp_contract_records',
            'page_url' => 'https://www.eprocure.gov.bd/resources/common/AdvSearchNOA.jsp',
            'endpoint' => 'https://www.eprocure.gov.bd/AdvSearchNOAServlet',
            'default_page_size' => 10,
            'preview_columns' => [
                ['key' => 'tender_id', 'label' => 'Tender ID'],
                ['key' => 'invitation_ref_no', 'label' => 'Reference'],
                ['key' => 'tender_title', 'label' => 'Title'],
                ['key' => 'procuring_entity', 'label' => 'Procuring Entity'],
                ['key' => 'district', 'label' => 'District'],
                ['key' => 'notification_of_award_raw', 'label' => 'NOA Date'],
                ['key' => 'contract_award_to', 'label' => 'Award To'],
                ['key' => 'contract_value_raw', 'label' => 'Value'],
            ],
        ],
        'eExperience' => [
            'label' => 'eExperience',
            'table' => 'egp_experience_records',
            'page_url' => 'https://www.eprocure.gov.bd/resources/common/SearcheCMS.jsp?v=advSearch',
            'endpoint' => 'https://www.eprocure.gov.bd/AdvSearcheCMSServlet',
            'default_page_size' => 10,
            'preview_columns' => [
                ['key' => 'tender_id', 'label' => 'Tender ID'],
                ['key' => 'reference_no', 'label' => 'Reference'],
                ['key' => 'tender_title', 'label' => 'Title'],
                ['key' => 'contract_awarded_to', 'label' => 'Awarded To'],
                ['key' => 'company_unique_id', 'label' => 'Company ID'],
                ['key' => 'contract_amount_raw', 'label' => 'Amount'],
                ['key' => 'work_status', 'label' => 'Status'],
            ],
        ],
    ];
}
