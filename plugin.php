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
$App->set_action( 'api_response', 'modules_api_integration' );
$App->set_action( 'admin_nav', 'modules_admin_navigation' );
$App->set_action( 'rendered', 'modules_auto_update_patch' );
$App->set_action( 'admin_middle', 'modules_json_object' );
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
    $config[ 'auto' ] = true;
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
 * API integration
 * @param array $response
 * @return array
 */
function modules_api_integration( array $response ): array {
  global $App;
  
  if ( 'Routes' === $response[ 'message' ] ) {
    $response[ 'data' ][ 'api/' . API_VERSION . '/modules' ] = array();
    $response[ 'data' ][ 'api/' . API_VERSION . '/modules' ][ 'href' ] = $App->url( 'api/' . API_VERSION . '/modules' );
    $response[ 'data' ][ 'api/' . API_VERSION . '/modules' ][ 'methods' ] = [ 'GET', 'POST', 'PUT', 'DELETE' ];
  }
  
  elseif ( 404 === $response[ 'code' ] ) {
    $slug = $response[ 'data' ][ 'slug' ];
    $version = $response[ 'data' ][ 'version' ];
    if ( 'modules' !== $slug || API_VERSION !== $version ) {
      return $response;
    }
    
    $response[ 'data' ] = array();
    $slug = api_input_string( 'slug' );
    $type = api_input_string( 'type' );
    $response[ 'data' ][ 'slug' ] = $slug;
    $response[ 'data' ][ 'type' ] = $type;
    $module = get_module( $slug, $type );
    $method = api_method();
    
    if ( 'GET' === $method ) {
      $response[ 'code' ] = 200;
      if ( api_input_bool( 'list' ) ) {
        $response[ 'status' ] = true;
        $response[ 'message' ] = 'All modules';
        $response[ 'data' ] = get_modules();
        return $response;
      }
      
      elseif ( ! $module ) {
        $response[ 'message' ] = 'Module not found';
        return $response;
      }
      
      $response[ 'status' ] = true;
      $response[ 'message' ] = 'Module details';
      $module[ 'downloaded' ] = in_array( $slug, ( $type === 'theme' ? $App->themes : $App->plugins ) );
      $module[ 'size' ] = module_size( $module );
      $response[ 'data' ] = $module;
      return $response;
    }
    
    elseif ( 'POST' === $method ) {
      $response[ 'code' ] = 200;
      if ( ! download_module( $module, $msg ) ) {
        $response[ 'message' ] = $msg;
        return $response;
      }
      
      $response[ 'status' ] = true;
      $response[ 'message' ] = $msg;
      $module[ 'size' ] = module_size( $module );
      $response[ 'data' ] = $module;
      return $response;
    }
    
    elseif ( 'DELETE' === $method ) {
      $response[ 'code' ] = 200;
      if ( ! delete_module( $module, $msg ) ) {
        $response[ 'message' ] = $msg;
        return $response;
      }
      
      $response[ 'status' ] = true;
      $response[ 'message' ] = $msg;
      return $response;
    }
  }
  
  return $response;
}

/**
 * Manager panel link
 * @return string
 */
function modules_admin_navigation(): string {
  global $App, $page;
  $slug = $App->admin_url( '?page=modules_manager', true );
  $active = ( 'modules_manager' === $page ? ' ss-bg-cyan' : '' );
  return "<a href=\"$slug\" class=\"ss-btn ss-inverted ss-bd-none ss-white$active\">Modules</a>";
}

/**
 * Auto update security patches
 * @return void
 */
function modules_auto_update_patch(): void {
  global $App;
  $config = $App->get( 'modules' );
  if ( ! $config[ 'auto' ] ) return;
  
  $time = filectime( modules_temporary_dir( 'packages.json' ) );
  if ( time() >= ( $time + strtotime( '+1 week', 0 ) ) ) {
    modules_download_list();
  }
  
  $modules = get_modules();
  foreach ( $modules as $module ) {
    if (  ! $module[ 'version' ][ 'security' ] ) {
      continue;
    }
    
    elseif ( 'plugin' === $module[ 'type' ] ) {
      if ( ! in_array( $module[ 'slug' ], $App->plugins ) ) {
        continue;
      }
    }
    
    elseif ( 'theme' === $module[ 'type' ] ) {
      if ( ! in_array( $module[ 'slug' ], $App->themes ) ) {
        continue;
      }
    }
    
    $slug = $module[ 'slug' ];
    $type = $module[ 'type' ];
    $current = get_module_local( $slug, $type, 'version', '0' );
    if ( version_compare( $module[ 'version' ][ 'tag' ], $current, '>' ) ) {
      download_module( $module );
    }
  }
}

/**
 * Modules object
 * @return string
 */
function modules_json_object(): string {
  $json = json_encode( get_modules() );
  return <<<EOL
  <script>const modules={$json}</script>
  EOL;
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
      $modules = get_modules();
      $themes  = $App->themes;
      $plugins = $App->plugins;
      $installed_plugins = $App->data()[ 'installed' ];
      if ( empty( $modules ) ) {
        $layout[ 'content' ] .= '<span class="ss-large">REPOSITORY EMPTY</span>';
      }
      
      foreach ( $modules as $module ) {
        $dependencies = '';
        $compatible = module_compatible( $module, $App->version );
        $is_current_theme = ( 'theme' === $module[ 'type' ] && $module[ 'slug' ] === $App->get( 'theme' ) );
        $downloaded = in_array( $module[ 'slug' ], ( $module[ 'type' ] === 'plugin' ? $plugins : $themes ) );
        $installed = in_array( $module[ 'slug' ], ( $module[ 'type' ] === 'plugin' ? $installed_plugins : [] ) );
        
        $layout[ 'content' ] .= '
        <div id="' . $module[ 'type' ] . '_' . $module[ 'slug' ] . '" class="ss-col ss-half ss-mb-5">
          <div class="ss-card ss-auto" style="width:95%">';
        
        if ( $module[ 'preview' ] ) {
          $layout[ 'content' ] .= '
          <img loading="lazy" width="1280" height="720" src="https://cdn.jsdelivr.net/gh/BoidCMS/Packages/' . $module[ 'type' ] . '/' . $module[ 'slug' ] . '/preview.webp" alt="' . $module[ 'name' ] . ' preview" class="ss-image ss-w-10">';
        } else {
          $layout[ 'content' ] .= '
          <img loading="lazy" width="1280" height="720" src="https://cdn.jsdelivr.net/gh/BoidCMS/Packages/preview.webp" alt="No preview" class="ss-image ss-w-10">';
        }
        
        $layout[ 'content' ] .= '
        <div class="ss-container">
          <p class="ss-tag ss-round ss-mb-0">' . strtoupper( $module[ 'type' ] ) . '</p>';
        
        if ( $downloaded ) {
          $layout[ 'content' ] .= '
          <p class="ss-tag ss-round ss-mb-0 ss-bg-gray">DOWNLOADED: v' . get_module_local( $module[ 'slug' ], $module[ 'type' ], 'version', '0.x.x' ) . '</p>
          <p class="ss-tag ss-round ss-mb-0 ss-bg-cyan">SIZE: ' . module_size( $module ) . '</p>';
        }
        
        $layout[ 'content' ] .= '
        <p class="ss-tag ss-tooltip ss-round ss-mb-0 ss-bg-brand">
          LATEST: v' . $module[ 'version' ][ 'tag' ];
        
        $version = version_compare( $module[ 'version' ][ 'tag' ], get_module_local( $module[ 'slug' ], $module[ 'type' ], 'version', '0' ) );
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
          <a' . ( ( $compatible && $version !== 0 ) ? ' href="' . $App->admin_url( '?page=modules_manager&download=true' . ( $downloaded ? '&update=true' : '' ) . '&slug=' . $module[ 'slug' ] . '&type=' . $module[ 'type' ] . '&token=' . $App->token(), true ) . '" onclick="return confirm(\'Are you sure you want to ' . ( $downloaded ? ( ( $version < 0 ) ? 'downgrade' : 'update' ) : 'download' ) . ' this ' . $module[ 'type' ] . '?\')"' : '' ) . ' class="ss-button ss-card' . ( ( $compatible && $version !== 0 ) ? '' : ' ss-disabled' ) . '" disabled>' . ( $downloaded ? ( ( $version < 0 ) ? 'Downgrade' : ( ( $version === 0 ) ? 'Up to Date' : 'Update' ) ) : 'Download' ) . '</a>
          ' . ( $downloaded ? '<a' . ( ( $installed || $is_current_theme ) ? '' : ' href="' . $App->admin_url( '?page=modules_manager&delete=true&slug=' . $module[ 'slug' ] . '&type=' . $module[ 'type' ] . '&token=' . $App->token(), true ) . '" onclick="return confirm(\'Are you sure you want to delete this ' . $module[ 'type' ] . '?\')"' ) . ' class="ss-button ss-card ss-white ss-bg-light-red' . ( ( $installed || $is_current_theme ) ? ' ss-disabled" disabled' : '"' ) . '>Delete</a>' : '' ) . '
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
        <p>Dependencies:<br> <b class="ss-responsive">';
        foreach ( $module[ 'dependencies' ] as $addon ) {
          if ( 'plugin' === $addon[ 'type' ] || 'theme' === $addon[ 'type' ] ) {
            $dependencies .= sprintf( '%s %s (%s), ', ucfirst( $addon[ 'type' ] ), ucwords( str_replace( '-', ' ', $addon[ 'slug' ] ) ), $addon[ 'slug' ] );
          }
        }
        
        $layout[ 'content' ] .= rtrim( $dependencies, ' ,' ) . '
        </b></p>
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
          $App->alert( 'List updated successfully.', 'success' );
          $App->go( $App->admin_url( '?page=modules_manager' ) );
        }
        
        $App->alert( 'Failed to update list, please try again.', 'error' );
        $App->go( $App->admin_url( '?page=modules_manager' ) );
      }
      
      elseif ( isset( $_GET[ 'download' ] ) ) {
        $App->auth( post: false );
        $module = get_module( $_GET[ 'slug' ], $_GET[ 'type' ] );
        if ( download_module( $module, $msg ) ) {
          $action = ( isset( $_GET[ 'update' ] ) ? 'updated' : 'downloaded' );
          $App->alert( sprintf( '%s <b>%s</b> has been %s successfully.', ucfirst( $module[ 'type' ] ), ucwords( $module[ 'name' ] ), $action ), 'success' );
          $App->go( $App->admin_url( '?page=modules_manager' ) );
        }
        
        $App->alert( $msg . ', please try again.', 'error' );
        $App->go( $App->admin_url( '?page=modules_manager' ) );
      }
      
      elseif ( isset( $_GET[ 'delete' ] ) ) {
        $App->auth( post: false );
        $module = get_module( $_GET[ 'slug' ], $_GET[ 'type' ] );
        if ( delete_module( $module, $msg ) ) {
          $App->alert( $msg . ' successfully.', 'success' );
          $App->go( $App->admin_url( '?page=modules_manager' ) );
        }
        
        $App->alert( $msg . ', please try again.', 'error' );
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
  $remote = modules_repository( $module[ 'type' ] . '/' . $module[ 'slug' ] . '/' . $module[ 'version' ][ 'tag' ] . '.zip' );
  return modules_remote_copy( $remote, modules_temporary_dir( $module[ 'type' ] . '/' . $module[ 'slug' ] . '.zip' ) );
}

/**
 * Extract downloaded module
 * @param array $module
 * @return bool
 */
function extract_module_zipfile( array $module ): bool {
  $tempfile = modules_temporary_dir( $module[ 'type' ] . '/' . $module[ 'slug' ] . '.zip' );
  if ( ! is_file( $tempfile ) ) {
    return false;
  }
  
  $zip = new ZipArchive;
  if ( true === $zip->open( $tempfile ) ) {
    $dir = module_permanent_dir( $module );
    $zip->extractTo( $dir, $module[ 'files' ] );
    unlink( $tempfile );
    return $zip->close();
  }
  
  unlink( $tempfile );
  return false;
}

/**
 * Download module
 * @param ?array $module
 * @param ?string &$msg
 * @return bool
 */
function download_module( ?array $module, ?string &$msg = null ): bool {
  global $App;
  
  if ( ! $module ) {
    $msg = 'Module not found';
    return false;
  }
  
  elseif ( ! module_compatible( $module, $App->version ) ) {
    $msg = 'Module not compatible';
    return false;
  }
  
  foreach ( $module[ 'dependencies' ] as $i => $addon ) {
    if ( 'php' === $addon[ 'type' ] ) {
      $compatible = version_compare( PHP_VERSION, $addon[ 'version' ], '>=' );
      if ( $compatible ) {
        continue;
      }
      
      $msg = sprintf( 'PHP version is not compatible, requires %s+', $addon[ 'version' ] );
      return false;
    }
    
    elseif ( 'extension' === $addon[ 'type' ] ) {
      $loaded = extension_loaded( $addon[ 'name' ] );
      if ( $loaded ) {
        continue;
      }
      
      $msg = sprintf( 'Extension <b>%s</b> not loaded', $addon[ 'name' ] );
      return false;
    }
    
    elseif ( 'plugin' === $addon[ 'type' ] ) {
      if ( ! $App->installed( $addon[ 'slug' ] ) ) {
        $msg = sprintf( 'Plugin <b>%s</b> required', $addon[ 'name' ] );
        return false;
      }
    }
    
    elseif ( 'theme' === $addon[ 'type' ] ) {
      if ( $App->get( 'theme' ) !== $addon[ 'slug' ] ) {
        $msg = sprintf( 'Theme <b>%s</b> required', $addon[ 'name' ] );
        return false;
      }
    }
  }
  
  if ( ! download_module_zipfile( $module ) ) {
    $msg = 'Failed to download zip file';
    return false;
  }
  
  elseif ( ! extract_module_zipfile( $module ) ) {
    $msg = 'Failed to extract zip content';
    return false;
  }
  
  $msg = 'Module downloaded';
  return true;
}

/**
 * Delete downloaded module
 * @param ?array $module
 * @param ?string &$msg
 * return bool
 */
function delete_module( ?array $module, ?string &$msg = null ): bool {
  global $App;
  
  if ( ! $module ) {
    $msg = 'Module not found';
    return false;
  }
  
  elseif ( 'plugin' === $module[ 'type' ] ) {
    if ( $App->installed( $module[ 'slug' ] ) ) {
      $msg = 'Cannot delete active plugin';
      return false;
    }
  }
  
  elseif ( 'theme' === $module[ 'type' ] ) {
    if ( $module[ 'slug' ] === $App->get( 'theme' ) ) {
      $msg = 'Cannot delete active theme';
      return false;
    }
  }
  
  $dir = module_permanent_dir( $module );
  if ( ! $dir ) {
    $msg = 'Failed to locate module';
    return false;
  }
  
  elseif ( ! modules_recursive_delete( $dir ) ) {
    $msg = 'Failed to delete module';
    return false;
  }
  
  $msg = 'Module deleted';
  return true;
}

/**
 * Module directory
 * @param array $module
 * @return ?string
 */
function module_permanent_dir( array $module ): ?string {
  if (
       empty( trim( $module[ 'slug' ] ) ) ||
       ( 'plugin' !== $module[ 'type' ] &&
          'theme' !== $module[ 'type' ] )
     ) {
    return null;
  }
  
  global $App;
  return $App->root( $module[ 'type' ] . 's/' . $module[ 'slug' ] . '/' );
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
 * Folder size checker
 * @param string $folder
 * @return int
 */
function modules_folder_size( string $folder ): int {
  $files = scandir( $folder, SCANDIR_SORT_NONE );
  if ( ! $files ) {
    return 0;
  }
  
  $size = 0;
  foreach ( $files as $each ) {
    if ( '.' === $each || '..' === $each ) {
      continue;
    }
    
    $link = ( $folder . '/' . $each );
    if ( is_file( $link ) ) {
      $size += filesize( $link );
    }
    
    elseif ( is_dir( $link ) ) {
      $size += modules_folder_size( $link );
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
  $to   = modules_temporary_dir( 'packages.json' );
  return  modules_remote_copy( $from, $to );
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
 * Downloaded module details
 * @param string $slug
 * @param string $type
 * @param ?string $option
 * @param mixed $alt
 * @return mixed
 */
function get_module_local( string $slug, string $type, ?string $option = null, mixed $alt = null ): mixed {
  global $App;
  $module = get_module( $slug, $type );
  if ( null === $module ) return $alt;
  
  $file = module_permanent_dir( $module );
  if ( 'theme' === $module[ 'type' ] ) {
    $file .= '/functions.php';
    if ( ! is_file( $file ) ) {
      return $alt;
    }
  }
  
  elseif ( 'plugin' === $module[ 'type' ] ) {
    $file .= '/plugin.php';
    if ( ! is_file( $file ) ) {
      return $alt;
    }
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
 * Find module
 * @param string $slug
 * @param string $type
 * @return ?array
 */
function get_module( string $slug, string $type ): ?array {
  $modules = get_modules();
  foreach ( $modules as $module ) {
    if (
         $slug === $module[ 'slug' ] &&
         $type === $module[ 'type' ]
       ) {
      return $module;
    }
  }
  
  return null;
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
function get_modules(): array {
  return json_decode( file_get_contents( modules_temporary_dir( 'packages.json' ) ), true );
}
?>
