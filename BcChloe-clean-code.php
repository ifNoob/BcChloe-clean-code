<?php
/*
Plugin Name: BcChloe Clean Code
Plugin URI: https://github.com/ifNoob/BcChloe-clean-code
Description: WordPress clean code | clean styletag | clean scripttag | ver disappear | minify html
Author: BcChloe
Author URI: https://bcchloe.jp
Text Domain: bcchloe-clean-code
Version: 1.0
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// Exit If Accessed Directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**==========================
* Define Constants
===========================*/
define( 'BC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BC_PLUGIN_URL', plugins_url( '' , __FILE__) );
//global $foo;

	add_action( 'init', array( 'BcChloe_Clean_Code', 'bcchloe_clean_code_init' ) );

class BcChloe_Clean_Code {

    protected $nonce_name = 'bcchloe-clean-code';
    protected $post_url = '';

		public $plugin_version = '1.0';

    public static function bcchloe_clean_code_init() {
        new self;
    }

	public function __construct() {
    add_action( 'init', array( &$this, 'bc_style_clean' ));
    add_action( 'init', array( &$this, 'bc_script_clean' ));
    add_action( 'init', array( &$this, 'bc_ver_kill' ));
    add_action( 'init', array( &$this, 'bc_wlwmanifest_kill' ));
    add_action( 'init', array( &$this, 'bc_minify_html' ));
	}

/**----------------
* Clean style tags
-----------------*/
  function bc_style_clean() {
    if ( ! is_user_logged_in() ) {
      add_filter('style_loader_tag', array($this, 'style_loader_tag'));
    }
  }
  function style_loader_tag($input) {
    preg_match_all("!<link rel='stylesheet'\s?(id='[^']+')?\s+href='(.*)' type='text/css' media='(.*)' />!", $input, $matches);
    $media = $matches[3][0] !== '' && $matches[3][0] !== 'all' ? ' media="' . $matches[3][0] . '"' : '';
    return '<link rel="stylesheet" href="' . $matches[2][0] . '"' . $media . '>' . "\n"; //TODO: remove new line option
  }

/**----------------
* Clean script tags
-----------------*/
  function bc_script_clean() {
    if ( ! is_user_logged_in() ) {
      add_filter('script_loader_tag', array($this, 'script_loader_tag'));
    }
  }
  function script_loader_tag($input) {
    $input = str_replace("type='text/javascript' ", '', $input);
    return str_replace("'", '"', $input);
  }

/**----------------
* Remove version parameter from css/js
-----------------*/
  function bc_ver_kill() {
    if ( ! is_user_logged_in() ) {
      add_filter( 'style_loader_src', array($this, 'remove_cssjs_ver'), 10, 2 );
      add_filter( 'script_loader_src', array($this, 'remove_cssjs_ver'), 10, 2 );
    }
  }
  function remove_cssjs_ver( $src ) {
    if ( strpos( $src, '?ver=' ) ) {
      $src = remove_query_arg( 'ver', $src );
    }
     return $src;
  }

/**----------------
* Remove wlwmanifest
-----------------*/
  function bc_wlwmanifest_kill() {
    remove_action( 'wp_head', 'wlwmanifest_link' );
  }

/**----------------
* Minify HTML
-----------------*/
  function bc_minify_html() {
    if ( ! is_user_logged_in() ) {
//    add_action('template_redirect', array($this, 'start'), -1);
      add_action( 'get_header', array( $this, 'start' ), - 1 );
    }
  }
  function start() {
    ob_start( array( $this, 'compress' ) );
  }
  function removeWhiteSpace( $html ) {
    $html = str_replace( array( "\t", "\r", "\n" ), ' ', $html );
    while ( strpos( $html, '  ' ) !== false ) {
      $html = str_replace( '  ', ' ', $html );
    }
    return $html;
  }

  function compress( $html ) {
    $pattern = '/<(?<script>script).*?<\/script\s*>|' .
               '<(?<style>style).*?<\/style\s*>|' .
               '<!(?<comment>--).*?-->|' .
               '<(?<tag>[\/\w.:-]*)(?:".*?"|\'.*?\'|[^\'">]+)*>|' .
               '(?<text>((<[^!\/\w.:-])?[^<]*)+)|/si';
    preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER );
    $doWhiteSpace = false;
    $raw_tag      = false;
    $makeRelative = false;
    $html         = '';
    foreach ( $matches as $token ) {
      $makeRelative = false;
      $tag          = ( isset( $token['tag'] ) ) ? strtolower( $token['tag'] ) : null;
      $content      = $token[0];
      if ( is_null( $tag ) ) {
        if ( ! empty( $token['script'] ) ) {
                    if(preg_match("/type=[\"|']text\/template[\"|']/si", $content)) {
                        $html .= $content;
                        continue;
                    }
          $makeRelative = $doWhiteSpace = $this->options->_theme_minify_html_inline_js;//Ignore inline JavaScript
        } else if ( ! empty( $token['style'] ) ) {
          $doWhiteSpace = $this->options->_theme_minify_html_inline_css; //Ignore inline Styles
        } else if ( $this->options->_theme_minify_html_remove_comments ) { //Ignore inline comments
          if ( $raw_tag != 'textarea' ) {
            // Save MSIE conditional comments
            $content      = preg_replace( '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $content );
            $makeRelative = true;
          }
        }
      } else {
        if ( 'pre' == $tag || 'textarea' == $tag ) {
          $raw_tag = $tag;
        } else if ( '/pre' == $tag || '/textarea' == $tag ) {
          $raw_tag = false;
        } else {
          if ( $raw_tag ) {
            $doWhiteSpace = false;
          } else {
            if ( $tag !== '' ) {
              if ( strpos( $tag, '/' ) === false ) {
                $content = preg_replace( '/(\s+)(\w++(?<!action|alt|content|src)=(""|\'\'))/i', '$1', $content );
              }
              $content = preg_replace( '/\s+(\/?\>)/', '$1', $content );
              if ( $tag !== 'link' || preg_match( '/rel=(?:\'|\")\s*canonical\s*(?:\'|\")/i', $content ) === 0 ) {
                $makeRelative = true;
              }
            } else {
              if ( strrpos( $html, ' ' ) === strlen( $html ) - 1 ) {
                $content = preg_replace( '/^[\s\r\n]+/', '', $content );
              }
            }
            $doWhiteSpace   = true;
          }
        }
      }
      if ( $this->options->_theme_minify_html_relative_urls && $makeRelative ) {
        $content = preg_replace_callback( '/(action|data|href|src)=(?:"([^"]*)"|\'([^\']*)\')/i', array( $this, 'relative' ), $content );
      }
      if ( $doWhiteSpace ) {
        $content = $this->removeWhiteSpace( $content );
      }
      $html .= $content;
    }
//    var_dump($matches);
    return $html;
  }

  protected function relative($matches) {
    global $absolute_to_relative_url_instance;
    $url = (!isset($matches[3])) ? $matches[2] : $matches[3];
    if ( ! isset( $absolute_to_relative_url_instance ) ) { $absolute_to_relative_url_instance = new Absolute_to_Relative_URLs(); }
    return $matches[1].'="'.$absolute_to_relative_url_instance->relate_url( $url, false, 2 ).'"';
  }
}
/*
Changed Absolute-to-Relative URLs 0.3.4 <https://github.com/stevenvachon/html-minify>
A class for use in shortening URL links.
Dual licensed under the MIT and GPL licenses.
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE
*/

if ( ! class_exists( 'Absolute_to_Relative_URLs' ) ) {
  class Absolute_to_Relative_URLs {
    //protected static $_instance;
    protected $custom_ports;
    protected $site_port_is_default;
    protected $site_url;
    /*
      $custom_site_url :: should be a valid URL, with scheme and host
      $custom_ports    :: e.g., array('ssh'=>22)
    */
    public function __construct( $custom_site_url = '', $custom_ports = array() ) {
      $this->custom_ports = $custom_ports;

      $this->get_site_url( $custom_site_url );

      if ( $this->site_url === false ) {
        trigger_error( 'Invalid site URL' );
      }
    }

    protected function build_url( $url, $output_type ) {
      $has_fragment = isset( $url['fragment'] );
      $has_resource = isset( $url['resource'] );
      $has_query    = isset( $url['query'] );
      if ( isset( $url['scheme'] ) ) {
        $first_half = $url['scheme'] . ':';
      } else {
        $first_half = '';
      }
      if ( isset( $url['host'] ) ) {
        $first_half .= '//';
        $user_or_pass = false;
        if ( isset( $url['user'] ) ) {
          $first_half .= $url['user'];
          $user_or_pass = true;
        }
        if ( isset( $url['pass'] ) ) {
          $first_half .= ':' . $url['pass'];
          $user_or_pass = true;
        }
        if ( $user_or_pass ) {
          $first_half .= '@';
        }
        $first_half .= $url['host'];
        if ( isset( $url['port'] ) ) {
          $first_half .= ':' . $url['port'];
        }
        $second_half = $url['path'];
      } else {
        if ( $output_type === 1 || $output_type === 2 ) {
          $absolute_path = $url['path'];
          $relative_path = ( isset( $url['path_relative'] ) ) ? $url['path_relative'] : false;
          if ( $relative_path !== false ) {
            if ( $output_type === 2 ) {
              $second_half = ( strlen( $relative_path ) <= strlen( $absolute_path ) ) ? $relative_path : $absolute_path;
            } else {
              $second_half = $relative_path;
            }
          } else {
            $second_half = $absolute_path;
          }
        } else if ( $output_type === 0 ) {
          $second_half = $url['path'];
        }
        if ( $has_resource || $has_query || $has_fragment ) {
          if ( $second_half === './' || ( $second_half === '/' && $this->site_url['path'] === '/' ) ) {
            $second_half = '';
          }
        }
      }
      if ( $has_resource ) {
        $second_half .= $url['resource'];
      }
      if ( $has_query ) {
        $second_half .= '?' . $url['query'];
      }
      if ( $has_fragment ) {
        $second_half .= '#' . $url['fragment'];
      }
      return $first_half . $second_half;
    }
    protected function get_default_port( $scheme ) {
      switch ( $scheme ) {
        case 'http'        :
          return 80;
        case 'https'    :
          return 443;
        case 'ftp'        :
          return 21;
      }
      foreach ( $this->custom_ports as $port_name => $port ) {
        $port_name = strtolower( $port_name );
        if ( $port_name === $scheme ) {
          return $port;
        }
      }
      // No listed match found
      return - 1;
    }
    protected function get_site_url( $custom_site_url ) {
      if ( $custom_site_url === '' ) {
        $this->custom_site_url = true;
        $url = ( ! isset( $_SERVER['HTTPS'] ) ) ? 'http://' : 'https://';
        if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
          $url .= $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'] . '@';
        }
        $url .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
      } else {
        $url = $custom_site_url;
      }
      $url = $this->parse_url( $url, true );
      if ( $url !== false ) {
        if ( isset( $url['port'] ) ) {
          $this->site_port_is_default = ( $url['port'] === $this->get_default_port( $url['scheme'] ) );
        } else {
          $url['port'] = $this->get_default_port( $url['scheme'] );
          $this->site_port_is_default = true;
        }
        $url['host_stripped'] = $this->remove_www( $url['host'] );
      }
      $this->site_url = $url;
    }
    /*
      Return an path string.
    */
    protected function implode_path( $path, $absolute_output ) {
      if ( ! empty( $path ) ) {
        $path = implode( '/', $path ) . '/';
        if ( $absolute_output ) {
          $path = '/' . $path;
        }
      } else {
        $path = ( ! $absolute_output ) ? './' : '/';
      }
      return $path;
    }
    /*
      Return an absolute path.
    */
    protected function parse_path( $path ) {
      if ( $path !== '/' ) {
        $path          = explode( '/', $path );
        $absolute_path = array();
        $first_dir = $path[0];
        // Check if not absolute: '/dir/' becomes array('','dir','')
        if ( $first_dir !== '' ) {
          if ( $first_dir === '.' || $first_dir === '..' ) {
            $path = array_merge( $this->site_url['path_array'], $path );
          }
        }
        foreach ( $path as $dir ) {
          if ( $dir !== '' ) {
            if ( $dir !== '..' ) {
              if ( $dir !== '.' ) {
                array_push( $absolute_path, $dir );
              }
            } else {
              $parent_index = count( $absolute_path ) - 1;

              if ( $parent_index >= 0 ) {
                unset( $absolute_path[ $parent_index ] );
                $absolute_path = array_values( $absolute_path );
              }
            }
          }
        }
        return $absolute_path;
      } else {
        // Faster to skip the above block and just create an array
        return array();
      }
    }
    /*
      Return the components of a URL.
    */
    protected function parse_url( $url, $init = false ) {
      if ( stripos( $url, 'data:' ) === 0 || stripos( $url, 'javascript:' ) === 0 ) {
        // Nothing can be done with data/javascript URIs
        return false;
      } else if ( strpos( $url, '//' ) === 0 ) {
        // Cannot parse scheme-relative URLs with parse_url
        $url = $this->site_url['scheme'] . ':' . $url;
      }
      // With PHP versions earlier than 5.3.3, an E_WARNING is emitted when URL parsing fails
      // REMOVE when WordPress enforces a higher version as it will increase performance
      $url = @parse_url( $url );
      if ( $url !== false ) {
        if ( $init ) {
          // Checks for host to catch "host:80"
          if ( ! isset( $url['scheme'] ) || ! isset( $url['host'] ) ) {
            // Invalid site url
            return false;
          }
        }
        if ( isset( $url['path'] ) ) {
          $path = str_replace( ' ', '%20', $url['path'] );
          $last_slash = strrpos( $path, '/' );
          if ( $last_slash !== false ) {
            $last_slash ++;
            if ( $last_slash < strlen( $path ) ) {
              // Isolate resource from path
              $url['resource'] = substr( $path, $last_slash );
              $path = substr( $path, 0, $last_slash );
            }
            $url['path_array'] = $this->parse_path( $path );
            $url['path']       = $this->implode_path( $url['path_array'], true );
          } else {
            // No slashes found
            $url['resource'] = $path;

            $url['path']       = $this->site_url['path'];
            $url['path_array'] = $this->site_url['path_array'];
          }
        } else if ( isset( $url['host'] ) ) {
          $url['path']       = '/';
          $url['path_array'] = array();
        } else {
          $url['path']       = $this->site_url['path'];
          $url['path_array'] = $this->site_url['path_array'];
        }
      }
      return $url;
    }
    /*
      Return a path relative to the site path.
    */
    protected function relate_path( $absolute_path ) {
      $relative_path = array();
      $site_path     = $this->site_url['path_array'];
      // At this point, it's related to the host
      $related      = true;
      $parent_index = - 1;
      // Find parents
      foreach ( $site_path as $i => $dir ) {
        if ( $related ) {
          $absolute_dir = ( isset( $absolute_path[ $i ] ) ) ? $absolute_path[ $i ] : null;
          if ( $dir !== $absolute_dir ) {
            $related = false;
          } else {
            $parent_index = $i;
          }
        }
        if ( ! $related ) {
          // Up one level
          array_push( $relative_path, '..' );
        }
      }
      // Form path
      foreach ( $absolute_path as $i => $dir ) {
        if ( $i > $parent_index ) {
          array_push( $relative_path, $dir );
        }
      }
      return $relative_path;
    }
    /*
      Return a URL relative to the site URL.

      $ignore_www  :: optionally, ignore "www" subdomain
      $output_type :: optionally, return either a:
          0: root-relative URL (/child-of-root/etc/)
          1: path-relative URL (../child-of-parent/etc/)
          2: shortest possible URL (root- or path-relative)
    */
    public function relate_url( $url, $ignore_www = false, $output_type = 2 ) {
      if ( $this->site_url !== false ) {
        if ( $url === '' || $url === '.' || $url === './' ) {
          if ( $this->site_url['path'] !== '/' ) {
            if ( $output_type === 1 || $output_type === 2 ) {
              return './';
            } else if ( $output_type === 0 ) {
              return $this->site_url['path'];
            }
          } else {
            return '/';
          }
        } else if ( $url === '/' ) {
          return '/';
        } else if ( $url === '#' ) {
          return '#';
        }
        $original_url = $url;
        $url = $this->parse_url( $url );
        if ( $url === false ) {
          // Unusable format
          return $original_url;
        }
      } else {
        // Invalid site url
        return $url;
      }
      $related = false;
      if ( isset( $url['scheme'] ) ) {
        $scheme = $url['scheme'];
        if ( $scheme === $this->site_url['scheme'] ) {
          unset( $url['scheme'] );
          if ( $ignore_www ) {
            $url['host'] = $this->remove_www( $url['host'] );
            $site_host = $this->site_url['host_stripped'];
          } else {
            $site_host = $this->site_url['host'];
          }
          if ( $url['host'] === $site_host ) {
            $related = true;
            if ( isset( $url['port'] ) ) {
              if ( $url['port'] === $this->site_url['port'] ) {
                unset( $url['port'] );
              } else {
                $related = false;
              }
            } else if ( ! $this->site_port_is_default ) {
              $related = false;
            }
            if ( isset( $url['user'] ) ) {
              if ( ! isset( $this->site_url['user'] ) || $url['user'] !== $this->site_url['user'] ) {
                $related = false;
              }
            }
            /*else if ( isset($this->site_url['user']) )
            {
              $related = false;
            }*/

            if ( isset( $url['pass'] ) ) {
              if ( ! isset( $this->site_url['pass'] ) || $url['pass'] !== $this->site_url['pass'] ) {
                $related = false;
              }
            }
            /*else if ( isset($this->site_url['pass']) )
            {
              $related = false;
            }*/

            if ( $related ) {
              unset( $url['host'], $url['user'], $url['pass'] );
            }
          } else if ( isset( $url['port'] ) ) {
            if ( $url['port'] === $this->get_default_port( $scheme ) ) {
              unset( $url['port'] );
            }
          }
        } else if ( isset( $url['port'] ) ) {
          if ( $url['port'] === $this->get_default_port( $scheme ) ) {
            unset( $url['port'] );
          }
        }
      }
      if ( ! isset( $url['host'] ) ) {
        $url['path_relative_array'] = $this->relate_path( $url['path_array'] );
        $url['path_relative']       = $this->implode_path( $url['path_relative_array'], false );
      }
      return $this->build_url( $url, $output_type );
    }
    protected function remove_www( $host ) {
      if ( strpos( $host, 'www.' ) === 0 ) {
        $host = substr( $host, 4 );
      }
      return $host;
    }
  }


} // end BcChloe_Clean_Code

if ( class_exists( 'BcChloe_Clean_Code' ) ) {
  new BcChloe_Clean_Code();
}

?>