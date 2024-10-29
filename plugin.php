<?php defined( 'App' ) or die( 'BoidCMS' );
/**
 *
 * Modules – One-Click Downloads for Themes and Plugins
 *
 * @package Plugin_Modules
 * @author Shuaib Yusuf Shuaib
 * @version 0.1.0
 */

if ( 'modules' !== basename( __DIR__ ) ) return;

global $App;
$App->set_action( 'install', 'modules_install' );
$App->set_action( 'uninstall', 'modules_uninstall' );
$App->set_action( 'admin_nav', 'modules_admin_navigation' );
$App->set_action( 'cron', 'modules_auto_update_patch' );
$App->set_action( 'admin', 'modules_admin' );

/**
 * Initialize Modules, first time install
 * @param string $plugin
 * @return void
 */
function modules_install( string $plugin ): void {
  global $App;
  if ( 'modules' === $plugin ) {
    $config = array();
    $config[ 'auto' ] = false;
    $App->set( $config, 'modules' );
    $dir = modules_temporary_dir();
    if ( ! is_dir( $dir ) ) {
      (
        mkdir( $dir ) &&
        mkdir( $dir . 'plugin' ) &&
        mkdir( $dir . 'theme' ) &&
        modules_download_list()
      );
    }
  }
}

/**
 * Free hosting space, while uninstalled
 * @param string $plugin
 * @return void
 */
function modules_uninstall( string $plugin ): void {
  global $App;
  if ( 'modules' === $plugin ) {
    modules_clear_temporary();
    $App->unset(  'modules' );
  }
}

/**
 * Manager panel link
 * @return string
 */
function modules_admin_navigation(): string {
  global $App, $page;
  $slug = $App->admin_url( '?page=modules_manager' );
  $active = ( 'modules_manager' === $page ? ' ss-bg-cyan' : '' );
  return "<a href=\"$slug\" class=\"ss-btn ss-inverted ss-bd-none ss-white$active\">Modules</a>";
}

/**
 * Admin settings
 * @return void
 */
function modules_admin(): void {
  global $App, $layout, $page;
  switch ( $page ) {
    case 'modules':
      $config = $App->get( 'modules' );
      $layout[ 'title' ] = 'Modules';
      $layout[ 'content' ] = '
      <form action="' . $App->admin_url( '?page=modules', true ) . '" method="post">
        <label for="auto" class="ss-label">Auto Update Security Patches</label>
        <select id="auto" name="auto" class="ss-select ss-mobile ss-w-6 ss-auto">
          <option value="true"' . ( $config[ 'auto' ] ? ' selected' : '' ) . '>Yes</option>
          <option value="false"' . ( $config[ 'auto' ] ? '' : ' selected' ) . '>No</option>
        </select>
        <p class="ss-small">Plugin <span class="ss-tag ss-round">CRON</span> required for automation</p>
        <input type="hidden" name="token" value="' . $App->token() . '">
        <input type="submit" name="save" value="Save" class="ss-btn ss-mobile ss-w-5">
      </form>';
      if ( isset( $_POST[ 'save' ] ) ) {
        $App->auth();
        $config[ 'auto' ] = filter_input( INPUT_POST, 'auto', FILTER_VALIDATE_BOOL );
        if ( $App->set( $config, 'modules' ) ) {
          $App->alert( 'Settings saved successfully.', 'success' );
          $App->go( $App->admin_url( '?page=modules' ) );
        }
        
        $App->alert( 'Failed to save settings, please try again.', 'error' );
        $App->go( $App->admin_url( '?page=modules' ) );
      }
      
      require_once $App->root( 'app/layout.php' );
      break;
    case 'modules_manager':
      $config = $App->get( 'modules' );
      $layout[ 'title' ] = 'Modules';
      $layout[ 'content' ] = '
      <div class="ss-container ss-center ss-mt-7">
        <input type="search" id="search" placeholder="Search" class="ss-input ss-mobile ss-w-6 ss-mx-auto">
        <select id="filter" class="ss-select ss-mobile ss-w-6 ss-auto ss-tiny">
          <option value="all">ALL</option>
          <option value="theme">THEMES</option>
          <option value="plugin">PLUGINS</option>
        </select>
        <p class="ss-right-align ss-tiny ss-mt-7 ss-mb-5 ss-mr-3">
          <a href="' . $App->admin_url( '?page=modules_manager&sync=true&token=' . $App->token(), true ) . '" class="ss-btn ss-inverted ss-white ss-bg-cyan ss-bd-cyan">Check for updates</a>
          <br> Last sync: ' . date( 'F j, Y – H:i', filectime( modules_temporary_dir( 'packages.json' ) ) ) . '
          <br> Repository: ' . $App->esc( modules_repository() ) . '
        </p>
        <div class="ss-row ss-small">';
      $modules = modules();
      $themes  = $App->themes;
      $plugins = $App->plugins;
      $installed_plugins = $App->data()[ 'installed' ];
      if ( empty( $modules ) ) {
        $layout[ 'content' ] .= '<span class="ss-large">REPOSITORY EMPTY</span>';
      }
      
      foreach ( $modules as $module ) {
        $compatible = module_compatible( $module, $App->version );
        $is_current_theme = ( 'theme' === $module[ 'type' ] && $module[ 'slug' ] === $App->get( 'theme' ) );
        $downloaded = in_array( $module[ 'slug' ], ( $module[ 'type' ] === 'plugin' ? $plugins : $themes ) );
        $installed = in_array( $module[ 'slug' ], ( $module[ 'type' ] === 'plugin' ? $installed_plugins : [] ) );
        
        $layout[ 'content' ] .= '
        <div id="' . $module[ 'type' ] . '_' . $module[ 'slug' ] . '" class="ss-col ss-half ss-mb-5">
          <div class="ss-card ss-auto" style="width:95%">';
        
        if ( $module[ 'preview' ] ) {
          $layout[ 'content' ] .= '
          <img src="' . $module[ 'preview' ] . '" alt="' . $module[ 'name' ] . ' preview" class="ss-image ss-w-10">';
        } else {
          $layout[ 'content' ] .= '
          <div class="ss-xlarge ss-py-6 ss-anim-kenburns ss-gray">NO PREVIEW</div>
          <hr class="ss-hr ss-mb-0">';
        }
        
        $layout[ 'content' ] .= '
        <div class="ss-container">
          <p class="ss-tag ss-round ss-mb-0">' . strtoupper( $module[ 'type' ] ) . '</p>';
        
        if ( $downloaded ) {
          $layout[ 'content' ] .= '
          <p class="ss-tag ss-round ss-mb-0 ss-bg-gray">DOWNLOADED: v' . module_option_local( $module[ 'slug' ], $module[ 'type' ], 'version', '0.x.x' ) . '</p>
          <p class="ss-tag ss-round ss-mb-0 ss-bg-cyan">SIZE: ' . module_size( $module ) . '</p>';
        }
        
        $layout[ 'content' ] .= '
        <p class="ss-tag ss-tooltip ss-round ss-mb-0 ss-bg-brand">
          LATEST: v' . $module[ 'version' ][ 'tag' ];
        
        $version = version_compare( $module[ 'version' ][ 'tag' ], module_option_local( $module[ 'slug' ], $module[ 'type' ], 'version', '0' ) );
        if ( $version === 1 ) {
          $layout[ 'content' ] .= '
          <span class="ss-khaki ss-bold"> &uarr;</span>';
        } elseif ( $version === -1 ) {
          $layout[ 'content' ] .= '
          <span class="ss-large ss-bold ss-khaki"> &darr;</span>';
        }
        
        $layout[ 'content' ] .= '
        <span class="ss-text">' . $module[ 'version' ][ 'changelog' ] . '</span>
        </p>';
        
        if ( ! $compatible ) {
          $layout[ 'content' ] .= '
          <p class="ss-tag ss-round ss-mb-0 ss-bg-red">INCOMPATIBLE</p>';
        }
        
        if ( $module[ 'version' ][ 'security' ] ) {
          $layout[ 'content' ] .= '
          <p class="ss-tag ss-round ss-mb-0 ss-bg-orange">SECURITY PATCH</p>';
        }
        
        $layout[ 'content' ] .= '
        <h4 class="ss-monospace">' . $module[ 'name' ] . ' <sup class="ss-small">(' . $module[ 'slug' ] . ')</sup></h4>
        <p>' . substr( $module[ 'description' ], 0, 300 ) . '</p>
        <p>
          <a' . ( ( $compatible && $version !== 0 ) ? ' href="' . $App->admin_url( '?page=modules_manager&download=true' . ( $downloaded ? '&update=true' : '' ) . '&slug=' . $module[ 'slug' ] . '&type=' . $module[ 'type' ] . '&token=' . $App->token(), true ) . '" onclick="return confirm(\'Are you sure you want to ' . ( $downloaded ? ( ( $version < 0 ) ? 'downgrade' : 'update' ) : 'download' ) . ' this ' . $module[ 'type' ] . '?\')' . ( empty( $module[ 'dependencies' ] ) ? '' : '&& confirm(\'By ' . ( $downloaded ? 'updating' : 'downloading' ) . ' this ' . $module[ 'type' ] . ', about ' . count( $module[ 'dependencies' ] ) . ' module(s) and their dependencies will also be downloaded (if not already).\')' ) . '"' : '' ) . ' class="ss-button ss-card' . ( ( $compatible && $version !== 0 ) ? '' : ' ss-disabled' ) . '" disabled>' . ( $downloaded ? ( ( $version < 0 ) ? 'Downgrade' : ( ( $version === 0 ) ? 'Up to Date' : 'Update' ) ) : 'Download' ) . '</a>
          ' . ( $downloaded ? '<a' . ( $is_current_theme ? '' : ' href="' . $App->admin_url( '?page=modules_manager&delete=true&slug=' . $module[ 'slug' ] . '&type=' . $module[ 'type' ] . '&token=' . $App->token(), true ) . '" onclick="return confirm(\'Are you sure you want to delete this ' . $module[ 'type' ] . '?\')"' ) . ' class="ss-button ss-card ss-white ss-bg-light-red' . ( $is_current_theme ? ' ss-disabled" disabled' : '"' ) . '>Delete</a>' : '' ) . '
        </p>
        <details class="ss-fieldset">
          <summary>More details</summary>
          <p>Author:<br> <b class="ss-responsive">' . $module[ 'author' ] . '</b></p>
          <p>Website:<br> <b class="ss-responsive">' . $module[ 'website' ] . '</b></p>';
        
        if ( isset( $module[ 'support' ] ) ) {
          $layout[ 'content' ] .= '
          <p>Support:<br> <b class="ss-responsive">' . $module[ 'support' ] . '</b></p>';
        }
        
        $layout[ 'content' ] .= '
        <p>Compatible With:<br> <b class="ss-responsive">' . $module[ 'version' ][ 'compatible' ] . '</b></p>
        <p>Dependencies:<br> <b class="ss-responsive">' . join( ', ', array_column( $module[ 'dependencies' ], 'name' ) ) . '</b></p>
        <p>License:<br> <b class="ss-responsive">' . $module[ 'license' ] . '</b></p>
        </details>
        </div>
        </div>
        </div>';
      }
      
      $layout[ 'content' ] .= '
      </div>
      </div>
      <script>
      let modules = ' . json_encode( $modules ) . '
      
      search.oninput = () => {
        let name = search.value.toLowerCase().trim()
        modules.find(i => {
          if (
            (
              i.name.toLowerCase().includes(name) ||
              i.description.toLowerCase().includes(name) ||
              i.keywords.toLowerCase().includes(name)
            ) &&
            (
              filter.value === i.type ||
              filter.value === "all"
            )
          ) {
            document.querySelector(`#${i.type}_${i.slug}`).classList.remove("ss-hide")
          } else {
            document.querySelector(`#${i.type}_${i.slug}`).classList.add("ss-hide")
          }
        })
      }
      
      filter.onchange = () => {
        let name = search.value.toLowerCase().trim()
        modules.find(i => {
          if (
            (
              filter.value === i.type ||
              filter.value === "all"
            ) &&
            (
              i.name.toLowerCase().includes(name) ||
              i.description.toLowerCase().includes(name) ||
              i.keywords.toLowerCase().includes(name)
            )
          ) {
            document.querySelector(`#${i.type}_${i.slug}`).classList.remove("ss-hide")
          } else {
            document.querySelector(`#${i.type}_${i.slug}`).classList.add("ss-hide")
          }
        })
      }
      </script>';
      if ( isset( $_GET[ 'sync' ] ) ) {
        $App->auth( post: false );
        if ( modules_download_list() ) {
          $App->alert( 'Modules list updated successfully.', 'success' );
          $App->go( $App->admin_url( '?page=modules_manager' ) );
        }
        
        $App->alert( 'Failed to update modules list, please try again.', 'error' );
        $App->go( $App->admin_url( '?page=modules_manager' ) );
      }
      
      elseif ( isset( $_GET[ 'download' ] ) ) {
        $App->auth( post: false );
        $module = module_option( $_GET[ 'slug' ], $_GET[ 'type' ] );
        
        if ( ! $module ) {
          $App->alert( 'Module not found, please try again.', 'warning' );
          $App->go( $App->admin_url( '?page=modules_manager' ) );
        }
        
        elseif ( install_module( $module, $failed_msg ) ) {
          $action = ( isset( $_GET[ 'update' ] ) ? 'updated' : 'downloaded' );
          $App->alert( sprintf( '%s <b>%s</b> has been %s successfully.', ucfirst( $module[ 'type' ] ), ucwords( $module[ 'name' ] ), $action ), 'success' );
          $App->go( $App->admin_url( '?page=modules_manager' ) );
        }
        
        $App->alert( $failed_msg . ', please try again.', 'error' );
        $App->go( $App->admin_url( '?page=modules_manager' ) );
      }
      
      elseif ( isset( $_GET[ 'delete' ] ) ) {
        $App->auth( post: false );
        $module = module_option( $_GET[ 'slug' ], $_GET[ 'type' ] );
        
        if ( ! $module ) {
          $App->alert( 'Module not found, please try again.', 'warning' );
          $App->go( $App->admin_url( '?page=modules_manager' ) );
        }
        
        elseif ( 'plugin' === $module[ 'type' ] ) {
          if ( ! in_array( $module[ 'slug' ], $App->plugins ) ) {
            $App->alert( 'Plugin not downloaded, please try again.', 'info' );
            $App->go( $App->admin_url( '?page=modules_manager' ) );
          }
          
          elseif ( $App->installed( $module[ 'slug' ] ) ) {
            $App->alert( 'You cannot delete an installed plugin, please uninstall it and try again.', 'error' );
            $App->go( $App->admin_url( '?page=modules_manager' ) );
          }
        }
        
        elseif ( 'theme' === $module[ 'type' ] ) {
          if ( ! in_array( $module[ 'slug' ], $App->themes ) ) {
            $App->alert( 'Theme not downloaded, please try again.', 'info' );
            $App->go( $App->admin_url( '?page=modules_manager' ) );
          }
          
          elseif ( $module[ 'slug' ] === $App->get( 'theme' ) ) {
            $App->alert( 'You cannot delete current active theme, please activate a different theme and try again.', 'error' );
            $App->go( $App->admin_url( '?page=modules_manager' ) );
          }
        }
        
        $dir = module_permanent_dir( $module );
        if ( modules_recursive_delete( $dir ) ) {
          $App->alert( sprintf( '%s <b>%s</b> has been deleted successfully.', ucfirst( $module[ 'type' ] ), ucwords( $module[ 'name' ] ) ), 'success' );
          $App->go( $App->admin_url( '?page=modules_manager' ) );
        }
        
        $App->alert( 'Failed to delete module, please try again.', 'error' );
        $App->go( $App->admin_url( '?page=modules_manager' ) );
      }
      
      require_once $App->root( 'app/layout.php' );
      break;
  }
}

/**
 * Download module zip
 * @param array $module
 * @return bool
 */
function download_module_zipfile( array $module ): bool {
  $tempfile = modules_temporary_dir( $module[ 'type' ] . '/' . $module[ 'slug' ] . '.zip' );
  return modules_remote_copy( $module[ 'version' ][ 'zip' ], $tempfile );
}

/**
 * Extract downloaded module
 * @param array $module
 * @return bool
 */
function extract_module_zipfile( array $module ): bool {
  $tempfile = modules_temporary_dir( $module[ 'type' ] . '/' . $module[ 'slug' ] . '.zip' );
  if ( ! is_file( $tempfile ) ) return false;
  
  $zip = new ZipArchive;
  if ( true === $zip->open( $tempfile ) ) {
    $dir = module_permanent_dir( $module );
    $zip->extractTo( $dir, $module[ 'files' ] );
    unlink( $tempfile );
    return $zip->close();
  }
  
  return false;
}

/**
 * Install module and its dependencies
 * @param array $module
 * @param ?string &$msg
 * @return bool
 */
function install_module( array $module, ?string &$msg = null ): bool {
  global $App;
  if ( ! module_compatible( $module, $App->version ) ) {
    $msg = 'Module not compatible';
    return false;
  }
  
  foreach ( $module[ 'dependencies' ] as $i => $addon ) {
    if ( 'php' === $addon[ 'type' ] ) {
      $compatible = version_compare( PHP_VERSION, $addon[ 'slug' ], '>=' );
      if ( $compatible ) {
        continue;
      }
      
      $msg = 'PHP version not compatible';
      return false;
    }
    
    elseif ( 'extension' === $addon[ 'type' ] ) {
      $loaded = extension_loaded( $addon[ 'slug' ] );
      if ( $loaded ) {
        continue;
      }
      
      $msg = sprintf( 'Extension "%s" not loaded', $addon[ 'slug' ] );
      return false;
    }
    
    $addon = module_option( $addon[ 'slug' ], $addon[ 'type' ] );
    if ( ! $addon ) {
      $msg = sprintf( 'Dependency "%s" not found', $module[ 'dependencies' ][ $i ][ 'name' ] );
      return false;
    }
    
    if ( 'plugin' === $addon[ 'type' ] ) {
      $downloaded = in_array( $addon[ 'slug' ], $App->plugins );
      if ( $downloaded ) {
        $App->install( $addon[ 'slug' ] );
        continue;
      }
    }
    
    elseif ( 'theme' === $addon[ 'type' ] ) {
      $downloaded = in_array( $addon[ 'slug' ], $App->themes );
      if ( $downloaded && $App->set( $addon[ 'slug' ], 'theme' ) ) {
        $App->get_action( 'change_theme', $addon[ 'slug' ] );
        continue;
      }
    }
    
    if ( ! install_module( $addon, $msg ) ) {
      return false;
    }
  }
  
  if ( ! download_module_zipfile( $module ) ) {
    $msg = 'Failed to download zip file';
    return false;
  }
  
  elseif ( ! extract_module_zipfile( $module ) ) {
    unlink( modules_temporary_dir( $module[ 'type' ] . '/' . $module[ 'slug' ] . '.zip' ) );
    $msg = 'Failed to extract zip content';
    return false;
  }
  
  elseif (
           'plugin' === $module[ 'type' ] ||
            'theme' === $module[ 'type' ]
         ) {
    return true;
  }
  
  $msg = 'An unexpected error occurred';
  return false;
}

/**
 * Module directory
 * @param array $module
 * @return ?string
 */
function module_permanent_dir( array $module ): ?string {
  global $App;
  if ( 'plugin' === $module[ 'type' ] || 'theme' === $module[ 'type' ] ) {
    return $App->root( $module[ 'type' ] . 's/' . $module[ 'slug' ] . '/' );
  }
  
  return null;
}

/**
 * Delete downloaded module
 * @param array $module
 * return bool
 */
function delete_module_dir( array $module ): bool {
  return modules_recursive_delete( module_permanent_dir( $module ) );
}

/**
 * File downloader
 * @param string $from
 * @param string $to
 * @return bool
 */
function modules_remote_copy( string $from, string $to ): bool {
  return copy( $from, $to, stream_context_create(
      array(
        'http' => array(
          'user_agent' => 'Modules | BoidCMS'
        )
      )
    )
  );
}

/**
 * Temporary directory
 * @param string $path
 * @return string
 */
function modules_temporary_dir( string $path = '' ): string {
  global $App;
  return $App->root( 'data/modules/' . $path );
}

/**
 * Delete temporary files
 * @return bool
 */
function modules_clear_temporary(): bool {
  return modules_recursive_delete( modules_temporary_dir() );
}

/**
 * Folder size
 * @param string $folder
 * @return int
 */
function modules_folder_size( string $folder ): int {
  $size = 0;
  $folder = ( rtrim( $folder, '/' ) . '/' );
  $folder = glob( $folder . '*', GLOB_NOSORT );
  foreach ( $folder as $each ) {
    if ( is_file( $each ) ) {
      $size += filesize( $each );
    } else {
      $size += modules_folder_size( $each );
    }
  }
  
  return $size;
}

/**
 * Recursive deletion
 * @param string $folder
 * @return bool
 */
function modules_recursive_delete( string $folder ): bool {
  $files = scandir( $folder, SCANDIR_SORT_NONE );
  if ( ! $files ) {
    return false;
  }
  
  foreach ( $files as $each ) {
    if ( '.' === $each || '..' === $each ) {
      continue;
    }
    
    $link = ( $folder . '/' . $each );
    if ( is_file( $link ) ) {
      unlink( $link );
    } else {
      modules_recursive_delete( $link );
    }
  }
  
  return rmdir( $folder );
}

/**
 * Download packages list
 * @return bool
 */
function modules_download_list(): bool {
  $from = modules_repository( 'packages.json' );
  $to = modules_temporary_dir( 'packages.json' );
  return modules_remote_copy( $from, $to );
}

/**
 * Modules repository
 * @param string $path
 * @return string
 */
function modules_repository( string $path = '' ): string {
  global $App;
  $repo = $App->get_filter( 'BoidCMS/Packages', 'modules.repo' );
  if ( empty( $path ) ) {
    return $repo;
  }
  
  return ( 'https://raw.githubusercontent.com/' . $repo . '/master/' . $path );
}

/**
 * Module compatible
 * @param array $module
 * @param string $version
 * @return bool
 */
function module_compatible( array $module, string $version ): bool {
  $current = $module[ 'version' ];
  if (
        '' === $current[ 'compatible' ] ||
       '*' === $current[ 'compatible' ]
     ) return true;
  
  if ( ! str_contains( $current[ 'compatible' ], ' ' ) ) {
    return ( $version === $current[ 'compatible' ] );
  }
  
  elseif ( str_contains( $current[ 'compatible' ], ' - ' ) ) {
    list( $from, $to ) = explode( ' - ', $current[ 'compatible' ] );
    return (
      version_compare( $version, $from, '>=' ) &&
      version_compare( $version, $to, '<=' )
    );
  }
  
  list( $operator, $tag ) = explode( ' ', $current[ 'compatible' ] );
  if ( ! in_array( $operator, [ '<', '<=', '>', '>=', '=', '==', '!=' ] ) ) {
    return false;
  }
  
  return version_compare( $version, $tag, $operator );
}

/**
 * Local module information
 * @param string $slug
 * @param string $type
 * @param ?string $option
 * @param mixed $alt
 * @return mixed
 */
function module_option_local( string $slug, string $type, ?string $option = null, mixed $alt = null ): mixed {
  global $App;
  $module = module_option( $slug, $type );
  if ( null === $module ) return $alt;
  if (
       ! in_array( $module[ 'slug' ], $App->themes ) &&
       ! in_array( $module[ 'slug' ], $App->plugins )
     ) {
    return $alt;
  }
  
  $file = module_permanent_dir( $module );
  switch ( $module[ 'type' ] ) {
    case 'theme':
      $file .= '/functions.php';
      if ( ! is_file( $file ) ) {
        return $alt;
      }
      break;
    case 'plugin':
      $file .= '/plugin.php';
      if ( ! is_file( $file ) ) {
        return $alt;
      }
      break;
    default:
      return $alt;
  }
  
  $count  = 0;
  $content = '';
  $handle = fopen( $file, 'r' );
  while ( ! feof( $handle ) && $count < 15 ) {
    $content .= fgets( $handle );
    $count++;
  }
  fclose( $handle );
  
  $valid = array( 'author', 'version', 'license' );
  $regexp = '/\@(' . join( '|', $valid ) . ')\s*([^\n]+)\n/i';
  preg_match_all( $regexp, $content, $matches );
  $module = array_combine( $matches[1], $matches[2] );
  if ( null === $option ) return $module;
  return ( $module[ $option ] ?? $alt );
}

/**
 * Module information
 * @param string $slug
 * @param string $type
 * @param ?string $option
 * @return mixed
 */
function module_option( string $slug, string $type, ?string $option = null ): mixed {
  $modules = modules();
  $index   = array_search( $slug, array_column( $modules, 'slug' ) );
  if ( false === $index ) {
    return null;
  }
  
  $module = $modules[ $index ];
  if ( $type !== $module[ 'type' ] ) {
    return null;
  }
  
  if ( null === $option ) {
    return $module;
  }
  
  return ( $module[ $option ] ?? null );
}

/**
 * Auto update security patches
 * @return void
 */
function modules_auto_update_patch(): void {
  global $App;
  $config = $App->get( 'modules' );
  if ( ! $config[ 'auto' ] ) return;
  
  $modules = modules();
  foreach ( $modules as $module ) {
    if (  ! $module[ 'version' ][ 'security' ] ) {
      continue;
    }
    
    elseif (
             'plugin' === $module[ 'type' ] &&
             ! $App->installed( $module[ 'slug' ] )
           ) {
      continue;
    }
    
    elseif (
             'theme' === $module[ 'type' ] &&
             $App->get( 'theme' ) !== $module[ 'slug' ]
           ) {
      continue;
    }
    
    $current = module_option_local( $module, 'version', '0' );
    if ( version_compare( $module[ 'version' ][ 'tag' ], $current, '>' ) ) {
      install_module( $module );
    }
  }
}

/**
 * Module folder size
 * @param array $module
 * @return string
 */
function module_size( array $module ): string {
  $folder = module_permanent_dir( $module );
  $size   = modules_folder_size(  $folder );
  $proper_size = round( $size / 1024 );
  $label  = 'KB';
  
  if ( $proper_size > 900 ) {
    $proper_size = round( $proper_size / 1024 );
    $label = 'MB';
  }
  
  return ( $proper_size . $label );
}

/**
 * List of modules
 * @return array
 */
function modules(): array {
  return json_decode( file_get_contents( modules_temporary_dir( 'packages.json' ) ), true );
}
?>
