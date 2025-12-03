# Ashwab WP Access and Hide

**Ashwab WP Access and Hide** is a powerful WordPress plugin designed to give administrators granular control over the WordPress dashboard. It allows you to restrict access to specific menu items, pages, and post types for all users except a designated administrator and excluded users.

## Features

-   **Restrict Access**: Block access to specific admin menu items, submenus, and post types.
-   **Dashboard Widget Hiding**: Hide specific dashboard widgets to declutter the interface for other users.
-   **CSS Hiding**: Hide any element in the dashboard using CSS selectors (Classes/IDs).
-   **Custom Redirects**: Configure custom redirects for restricted pages (e.g., redirect to a custom URL or a specific page).
-   **Access Logs**: Keep track of unauthorized access attempts with detailed logs (User, Time, URL, IP).
-   **Excluded Users**: Whitelist specific user IDs to bypass all restrictions.
-   **Stealth Mode**: Option to hide admin notices and even hide the plugin itself from the plugins list for restricted users.

## Installation

1.  Download the plugin zip file.
2.  Go to your WordPress Dashboard -> Plugins -> Add New.
3.  Click "Upload Plugin" and select the zip file.
4.  Activate the plugin.
5.  Upon activation, your current administrator email will be set as the "Allowed Admin".

## Usage

1.  **Access Settings**: Go to the "Access & Hide" menu in the admin dashboard.
2.  **General Tab**:
    -   Click "Add Element" to search and select pages or menus to restrict.
    -   Add User IDs to the "Excluded Users" list to grant them full access.
3.  **Redirects Tab**: Choose what happens when a user tries to access a restricted area (Default message, Custom URL, or Page ID).
4.  **Widgets Tab**: Enter the IDs of dashboard widgets you want to hide (e.g., `dashboard_primary`).
5.  **CSS Hiding Tab**: Enter CSS selectors (e.g., `.update-nag`, `#wp-admin-bar-new-content`) to hide specific elements.
6.  **Advanced Tab**:
    -   Toggle "Hide Admin Notices" to clean up the dashboard.
    -   Toggle "Hide Plugin" to make this plugin invisible to other users.

## Support

If you encounter any issues or have questions, please feel free to open an issue on the GitHub repository.

<div align="center">

**Made with ❤️ by [Essam Barghsh](https://www.esssam.com)**

[⬆ Back to Top](#ashwab-wp-access-and-hide)

</div>
