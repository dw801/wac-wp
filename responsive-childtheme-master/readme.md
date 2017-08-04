The WAC website uses the Responsive wordpress theme.  The best practice is that modifications to themes be made in a child theme (https://codex.wordpress.org/Child_Themes).  In wordpress this child theme is installed and it refers to the parent Responsive theme.

Our customizations are all in the stylesheet at responsive-childtheme-master/style.css

It is also common to use a child theme to modify the theme's functions.php file.  We have opted instead to include any php modifications in the wac membership plugin.  
