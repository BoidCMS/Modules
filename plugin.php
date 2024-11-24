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
       $App->installed( 'cron' ) and
       schedule_task( 'modules', 'modules_tasks' );
define( 'MODULES_REPO', 'BoidCMS/Packages' );

$App->set_action( 'install', function ( $slug ) {
  if ( 'modules' === $slug ) {
    $config = array();
    $config[ 'auto' ] = true;
    $this->set( $config, 'modules' );
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
  
  $resolved = array();
  $modules = get_modules();
  foreach ( $modules as $module ) {
    if ( ! in_array(  $module[ 'slug' ], $resolved ) ) {
      resolve_module( $module[ 'slug' ], $resolved );
    }
  }
  
  $installed = $this->database[ 'installed' ];
  foreach ( $resolved as $index => $plugin ) {
    if ( ! in_array( $plugin, $installed ) ) {
      unset( $resolved[ $index ] );
    }
  }
  
  $resolved = array_merge( $resolved, $installed );
  $this->database[ 'installed' ] = array_unique( $resolved );
});

$App->set_action( 'uninstall', function ( $slug ) {
  if ( 'modules' === $slug ) {
    modules_clear_temporary();
    $this->unset( 'modules' );
  }
});

$App->set_action( 'api_response', function ( $response ) {
  if ( 'Routes' === $response[ 'message' ] ) {
    $response[ 'data' ][ 'api/' . API_VERSION . '/modules' ] = array();
    $response[ 'data' ][ 'api/' . API_VERSION . '/modules' ][ 'href' ] = $this->url( 'api/' . API_VERSION . '/modules' );
    $response[ 'data' ][ 'api/' . API_VERSION . '/modules' ][ 'methods' ] = [ 'GET', 'POST', 'DELETE' ];
  }
  
  elseif ( 404 === $response[ 'code' ] ) {
    $slug = $response[ 'data' ][ 'slug' ];
    $version = $response[ 'data' ][ 'version' ];
    if ( 'modules' !== $slug || API_VERSION !== $version ) {
      return $response;
    }
    
    $response[ 'code' ] = 200;
    $response[ 'data' ] = array();
    $slug = api_input_string( 'slug' );
    $type = api_input_string( 'type' );
    $response[ 'data' ][ 'slug' ] = $slug;
    $response[ 'data' ][ 'type' ] = $type;
    $module = get_module( $slug, $type );
    $method = api_method();
    
    if ( 'GET' === $method ) {
      if ( api_input_bool( 'list' ) ) {
        $response[ 'status' ] = true;
        $response[ 'message' ] = 'All modules';
        $response[ 'data' ] = get_modules();
        return $response;
      }
      
      elseif ( ! $module ) {
        $response[ 'code' ] = 404;
        $response[ 'message' ] = 'Module not found';
        return $response;
      }
      
      $response[ 'status' ] = true;
      $response[ 'message' ] = 'Module details';
      $module[ 'downloaded' ] = in_array( $slug, ( $type === 'theme' ? $this->themes : $this->plugins ) );
      $response[ 'data' ] = $module;
      return $response;
    }
    
    elseif ( 'POST' === $method ) {
      if ( ! $module ) {
        $response[ 'code' ] = 404;
        $response[ 'message' ] = 'Module not found';
        return $response;
      }
      
      elseif ( ! download_module( $module, $msg ) ) {
        $response[ 'message' ] = $msg;
        return $response;
      }
      
      $response[ 'status' ] = true;
      $response[ 'message' ] = $msg;
      $response[ 'data' ] = $module;
      return $response;
    }
    
    elseif ( 'DELETE' === $method ) {
      if ( ! $module ) {
        $response[ 'code' ] = 404;
        $response[ 'message' ] = 'Module not found';
        return $response;
      }
      
      elseif ( ! delete_module( $module, $msg ) ) {
        $response[ 'message' ] = $msg;
        return $response;
      }
      
      $response[ 'status' ] = true;
      $response[ 'message' ] = $msg;
      return $response;
    }
  }
  
  return $response;
});

$App->set_action( 'admin_nav', function () {
  global $page;
  $slug = $this->admin_url( '?page=marketplace', true );
  $active = ( 'marketplace' === $page ? ' ss-bg-cyan' : '' );
  return "<a href=\"$slug\" class=\"ss-btn ss-inverted ss-bd-none ss-white$active\">Marketplace</a>";
});

$App->set_action( 'admin_middle', function () {
  $json = json_encode( get_modules() );
  return <<<EOL
  <script>const modules={$json}</script>
  EOL;
});

$App->set_action( 'admin', function () {
  global $layout, $page;
  switch ( $page ) {
    case 'modules':
      $config = $this->get( 'modules' );
      $layout[ 'title' ] = 'Modules';
      $layout[ 'content' ] = '
      <form action="' . $this->admin_url( '?page=modules', true ) . '" method="post">
        <label for="auto" class="ss-label">Auto Update Security Patches</label>
        <select id="auto" name="auto" class="ss-select ss-mobile ss-w-6 ss-auto">
          <option value="true"' . ( $config[ 'auto' ] ? ' selected' : '' ) . '>Yes</option>
          <option value="false"' . ( $config[ 'auto' ] ? '' : ' selected' ) . '>No</option>
        </select>
        <input type="hidden" name="token" value="' . $this->token() . '">
        <input type="submit" name="save" value="Save" class="ss-btn ss-mobile ss-w-5">
      </form>';
      if ( isset( $_POST[ 'save' ] ) ) {
        $this->auth();
        $config[ 'auto' ] = filter_input( INPUT_POST, 'auto', FILTER_VALIDATE_BOOL );
        if ( $this->set( $config, 'modules' ) ) {
          $this->alert( 'Settings saved successfully.', 'success' );
          $this->go( $this->admin_url( '?page=modules' ) );
        }
        
        $this->alert( 'Failed to save settings, please try again.', 'error' );
        $this->go( $this->admin_url( '?page=modules' ) );
      }
      
      require_once $this->root( 'app/layout.php' );
      break;
    case 'marketplace':
      $config = $this->get( 'modules' );
      $layout[ 'title' ] = 'Marketplace';
      $layout[ 'content' ] = '
      <div class="ss-container ss-center ss-mt-7">
        <input type="search" id="search" placeholder="Search" class="ss-input ss-mobile ss-w-6 ss-mx-auto">
        <select id="filter" class="ss-select ss-mobile ss-w-6 ss-auto ss-tiny">
          <option value="all">ALL</option>
          <option value="theme">THEMES</option>
          <option value="plugin">PLUGINS</option>
        </select>
        <p class="ss-right-align ss-tiny ss-mt-7 ss-mb-5 ss-mr-3">
          <a href="' . $this->admin_url( '?page=marketplace&sync=true&token=' . $this->token(), true ) . '" class="ss-btn ss-inverted ss-white ss-bg-cyan ss-bd-cyan">Check for updates</a>
          <br> Last sync: ' . date( 'F j, Y – H:i', filectime( modules_temporary_dir( 'packages.json' ) ) ) . '
          <br> Repository: ' . MODULES_REPO . '
        </p>
        <div class="ss-row ss-small">';
      $modules = get_modules();
      $themes  = $this->themes;
      $plugins = $this->plugins;
      $installed_plugins = $this->data()[ 'installed' ];
      if ( empty( $modules ) ) {
        $layout[ 'content' ] .= '<span class="ss-large">REPOSITORY EMPTY</span>';
      }
      
      foreach ( $modules as $module ) {
        $dependencies = '';
        $compatible = module_compatible( $module, $this->version );
        $is_current_theme = ( 'theme' === $module[ 'type' ] && $module[ 'slug' ] === $this->get( 'theme' ) );
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
          <p class="ss-tag ss-round ss-mb-0 ss-bg-gray">DOWNLOADED: v' . get_module_local( $module[ 'slug' ], $module[ 'type' ], 'version', '0.x.x' ) . '</p>';
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
          <a' . ( ( $compatible && $version !== 0 ) ? ' href="' . $this->admin_url( '?page=marketplace&download=true' . ( $downloaded ? '&update=true' : '' ) . '&slug=' . $module[ 'slug' ] . '&type=' . $module[ 'type' ] . '&token=' . $this->token(), true ) . '" onclick="return confirm(\'Are you sure you want to ' . ( $downloaded ? ( ( $version < 0 ) ? 'downgrade' : 'update' ) : 'download' ) . ' this ' . $module[ 'type' ] . '?\')"' : '' ) . ' class="ss-button ss-card' . ( ( $compatible && $version !== 0 ) ? '' : ' ss-disabled' ) . '" disabled>' . ( $downloaded ? ( ( $version < 0 ) ? 'Downgrade' : ( ( $version === 0 ) ? 'Up to Date' : 'Update' ) ) : 'Download' ) . '</a>
          ' . ( $downloaded ? '<a' . ( ( $installed || $is_current_theme ) ? '' : ' href="' . $this->admin_url( '?page=marketplace&delete=true&slug=' . $module[ 'slug' ] . '&type=' . $module[ 'type' ] . '&token=' . $this->token(), true ) . '" onclick="return confirm(\'Are you sure you want to delete this ' . $module[ 'type' ] . '?\')"' ) . ' class="ss-button ss-card ss-white ss-bg-light-red' . ( ( $installed || $is_current_theme ) ? ' ss-disabled" disabled' : '"' ) . '>Delete</a>' : '' ) . '
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
        $this->auth( post: false );
        if ( modules_download_list() ) {
          $this->alert( 'List updated successfully.', 'success' );
          $this->go( $this->admin_url( '?page=marketplace' ) );
        }
        
        $this->alert( 'Failed to update list, please try again.', 'error' );
        $this->go( $this->admin_url( '?page=marketplace' ) );
      }
      
      elseif ( isset( $_GET[ 'download' ] ) ) {
        $this->auth( post: false );
        $module = get_module( $_GET[ 'slug' ], $_GET[ 'type' ] );
        if ( download_module( $module, $msg ) ) {
          $action = ( isset( $_GET[ 'update' ] ) ? 'updated' : 'downloaded' );
          $this->alert( sprintf( '%s <b>%s</b> has been %s successfully.', ucfirst( $module[ 'type' ] ), ucwords( $module[ 'name' ] ), $action ), 'success' );
          $this->go( $this->admin_url( '?page=marketplace' ) );
        }
        
        $this->alert( $msg . ', please try again.', 'error' );
        $this->go( $this->admin_url( '?page=marketplace' ) );
      }
      
      elseif ( isset( $_GET[ 'delete' ] ) ) {
        $this->auth( post: false );
        $module = get_module( $_GET[ 'slug' ], $_GET[ 'type' ] );
        if ( delete_module( $module, $msg ) ) {
          $this->alert( $msg . ' successfully.', 'success' );
          $this->go( $this->admin_url( '?page=marketplace' ) );
        }
        
        $this->alert( $msg . ', please try again.', 'error' );
        $this->go( $this->admin_url( '?page=marketplace' ) );
      }
      
      require_once $this->root( 'app/layout.php' );
      break;
  }
});

/**
 * Auto update security patches
 * @return void
 */
function modules_tasks(): void {
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
 * Resolve module dependencies
 * @param string $slug
 * @param array &$resolved
 * @return void
 */
function resolve_module( string $slug, array &$resolved ): void {
  $module = get_module( $slug, 'plugin' );
  if ( ! $module || 'plugin' !== $module[ 'type' ] ) {
    return;
  }
  
  foreach ( $module[ 'dependencies' ] as $addon ) {
    if ( ! in_array(  $addon[ 'slug' ], $resolved ) ) {
      resolve_module( $addon[ 'slug' ], $resolved );
    }
  }
  
  if ( ! in_array( $slug, $resolved ) ) {
    $resolved[] =  $slug;
  }
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
  $App->get_action( 'download_module', $module );
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
  $App->get_action( 'delete_module', $module );
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
  return ( 'https://raw.githubusercontent.com/' . MODULES_REPO . '/master/' . $path );
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
 * List of modules
 * @return array
 */
function get_modules(): array {
  return json_decode( file_get_contents( modules_temporary_dir( 'packages.json' ) ), true );
}
?>
