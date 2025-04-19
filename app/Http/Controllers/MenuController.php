<?php

namespace App\Http\Controllers;

class MenuController extends Controller
{
    function getTopmenu()
    {

        $menuItems = [
            [
                'label' => 'Dashboard',
                'name' => 'dashboard',
            ],
            [
                'label' => 'Orders',
                'name' => 'orders',
            ],
            [
                'label' => 'Invoices',
                'name' => 'invoices',
            ],
            [
                'label' => 'Customers',
                'name' => 'customers',
            ],
            [
                'label' => 'Products',
                'name' => 'products',
            ],

            [
                'label' => 'Settings',
                'name' => 'settings',
            ],
        ];

        return $menuItems;
    }

    function getSideMenu()
    {

        $menuItems = [
            [
                'topMenu' => 'dashboard',
                'icon' => 'mdi-home',
                'module' => 'home',
                'title' => 'Home',
                'to' => '/',
                'menu' => 'dashboard',
            ],
            [
                'topMenu' => 'orders',
                'icon' => 'mdi-ticket-account',
                'module' => 'orders',
                'title' => 'Orders',
                'to' => '/order',
                'menu' => 'source_access',
            ],
            [
                'topMenu' => 'customers',
                'icon' => 'mdi-ticket-account',
                'module' => 'customers',
                'title' => 'Customers',
                'to' => '/customer',
                'menu' => 'source_access',
            ],
            [
                'topMenu' => 'products',
                'icon' => 'mdi-package',
                'module' => 'products',
                'title' => 'Products',
                'to' => '/product',
                'menu' => 'source_access',
            ],
            [
                'topMenu' => 'invoices',
                'icon' => 'mdi-package',
                'module' => 'invoices',
                'title' => 'Invoices',
                'to' => '/invoice',
                'menu' => 'source_access',
            ],
            [
                'topMenu' => 'settings',
                'icon' => 'mdi-briefcase-outline',
                'module' => 'setting',
                'title' => 'Business Source',
                'to' => '/business_source',
                'menu' => 'setting',
            ],
            [
                'topMenu' => 'settings',
                'icon' => 'mdi-credit-card-outline',
                'module' => 'setting',
                'title' => 'Payment Mode',
                'to' => '/payment_mode',
                'menu' => 'setting',
            ],
            [
                'topMenu' => 'settings',
                'icon' => 'mdi-shape-outline',
                'module' => 'setting',
                'title' => 'Product Category',
                'to' => '/product_category',
                'menu' => 'setting',
            ],
            [
                'topMenu' => 'settings',
                'icon' => 'mdi-shape-outline',
                'module' => 'setting',
                'title' => 'Delivery Service',
                'to' => '/delivery_service',
                'menu' => 'setting',
            ],
        ];

        return $menuItems;
    }
}
