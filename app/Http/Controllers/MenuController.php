<?php
namespace App\Http\Controllers;

class MenuController extends Controller
{
    public function getTopmenu()
    {

        $menuItems = [
            [
                'label' => 'Dashboard',
                'name'  => 'dashboard',
            ],
            [
                'label' => 'Orders',
                'name'  => 'orders',
            ],
            [
                'label' => 'Invoices',
                'name'  => 'invoices',
            ],
            [
                'label' => 'Customers',
                'name'  => 'customers',
            ],
            // [
            //     'label' => 'Products',
            //     'name'  => 'products',
            // ],
            [
                'label' => 'Inventory',
                'name'  => 'inventory',
            ],
            [
                'label' => 'Accounts',
                'name'  => 'accounts',
            ],
            [
                'label' => 'Analyse',
                'name'  => 'analyse',
            ],
            [
                'label' => 'Settings',
                'name'  => 'settings',
            ],
        ];

        return $menuItems;
    }

    public function getSideMenu()
    {

        $menuItems = [
            [
                'topMenu' => 'dashboard',
                'icon'    => 'mdi-home',
                'module'  => 'home',
                'title'   => 'Home',
                'to'      => '/',
                'menu'    => 'dashboard',
            ],
            [
                'topMenu' => 'orders',
                'icon'    => 'mdi-package',
                'module'  => 'orders',
                'title'   => 'Orders',
                'to'      => '/order',
                'menu'    => 'source_access',
            ],
            [
                'topMenu' => 'orders',
                'icon'    => 'mdi-basket',
                'module'  => 'orders',
                'title'   => 'Orders Items',
                'to'      => '/order_items',
                'menu'    => 'source_access',
            ],
            [
                'topMenu' => 'customers',
                'icon'    => 'mdi-ticket-account',
                'module'  => 'customers',
                'title'   => 'Customers',
                'to'      => '/customer',
                'menu'    => 'source_access',
            ],
            [
                'topMenu' => 'products',
                'icon'    => 'mdi-package',
                'module'  => 'products',
                'title'   => 'Products',
                'to'      => '/product',
                'menu'    => 'source_access',
            ],
            [
                'topMenu' => 'invoices',
                'icon'    => 'mdi-package',
                'module'  => 'invoices',
                'title'   => 'Invoices',
                'to'      => '/invoice',
                'menu'    => 'source_access',
            ],
            [
                'topMenu' => 'inventory',
                'icon'    => 'mdi-view-dashboard-outline',
                'module'  => 'inventory',
                'title'   => 'Dashboard',
                'to'      => '/inventory',
                'menu'    => 'inventory_access',
            ],
            [
                'topMenu' => 'inventory',
                'icon'    => 'mdi-warehouse',
                'module'  => 'inventory',
                'title'   => 'Inventory List',
                'to'      => '/inventory/stock',
                'menu'    => 'inventory_access',
            ],
            [
                'topMenu' => 'inventory',
                'icon'    => 'mdi-package-variant-closed',
                'module'  => 'inventory',
                'title'   => 'Items',
                'to'      => '/inventory/items',
                'menu'    => 'inventory_access',
            ],
            [
                'topMenu' => 'inventory',
                'icon'    => 'mdi-account-tie-outline',
                'module'  => 'inventory',
                'title'   => 'Vendors',
                'to'      => '/inventory/vendors',
                'menu'    => 'inventory_access',
            ],
            [
                'topMenu' => 'inventory',
                'icon'    => 'mdi-clipboard-list-outline',
                'module'  => 'inventory',
                'title'   => 'Purchase Orders',
                'to'      => '/inventory/purchase-orders',
                'menu'    => 'inventory_access',
            ],
            [
                'topMenu' => 'inventory',
                'icon'    => 'mdi-alert-outline',
                'module'  => 'inventory',
                'title'   => 'Low Stock Alerts',
                'to'      => '/inventory/low-stock',
                'menu'    => 'inventory_access',
            ],
            [
                'topMenu' => 'inventory',
                'icon'    => 'mdi-link-variant',
                'module'  => 'inventory',
                'title'   => 'Stock Mapping',
                'to'      => '/inventory/mapping',
                'menu'    => 'inventory_access',
            ],
            [
                'topMenu' => 'inventory',
                'icon'    => 'mdi-database-plus-outline',
                'module'  => 'inventory',
                'title'   => 'Opening Stock',
                'to'      => '/inventory/opening-stock',
                'menu'    => 'inventory_access',
            ],
            [
                'topMenu' => 'accounts',
                'icon'    => 'mdi-file-document-outline',
                'module'  => 'accounts',
                'title'   => 'Accounts',
                'to'      => '/accounts',
                'menu'    => 'accounts_access',
            ],
            [
                'topMenu' => 'settings',
                'icon'    => 'mdi-cog-outline',
                'module'  => 'setting',
                'title'   => 'Setup',
                'to'      => '/setup',
                'menu'    => 'setting',
            ],
            [
                'topMenu' => 'analyse',
                'icon'    => 'mdi-chart-line',
                'module'  => 'analyse',
                'title'   => 'Sales Analysis',
                'to'      => '/analyse',
                'menu'    => 'accounts_access',
            ],
        ];

        return $menuItems;
    }
}
