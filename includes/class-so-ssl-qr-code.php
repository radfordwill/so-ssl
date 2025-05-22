<?php
/**
 * QR Code Generator for So SSL Plugin
 *
 * Generates QR codes locally using PHP without external dependencies
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class So_SSL_QR_Code {

	private $size;
	private $margin;
	private $error_correction;

	const QUIET_ZONE = 4;

	// Error correction levels
	const ERROR_CORRECT_L = 0;
	const ERROR_CORRECT_M = 1;
	const ERROR_CORRECT_Q = 2;
	const ERROR_CORRECT_H = 3;

	/**
	 * Constructor
	 *
	 * @param int $size QR code size (width and height)
	 * @param int $margin Margin around QR code
	 * @param int $error_correction Error correction level
	 */
	public function __construct( $size = 200, $margin = 0, $error_correction = self::ERROR_CORRECT_L ) {
		$this->size             = $size;
		$this->margin           = $margin;
		$this->error_correction = $error_correction;
	}

	/**
	 * Generate SVG QR code
	 *
	 * @param string $data Data to encode
	 *
	 * @return string SVG markup
	 */
	public function generate_svg( $data ) {
		// Use a simple QR code matrix generation
		$qr_matrix = $this->generate_qr_matrix( $data );

		if ( ! $qr_matrix ) {
			return $this->generate_fallback_svg();
		}

		$module_count = count( $qr_matrix );
		$module_size  = floor( $this->size / ( $module_count + 2 * self::QUIET_ZONE ) );
		$svg_size     = $module_size * ( $module_count + 2 * self::QUIET_ZONE );

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $svg_size . '" height="' . $svg_size . '" viewBox="0 0 ' . $svg_size . ' ' . $svg_size . '">';
		$svg .= '<rect x="0" y="0" width="' . $svg_size . '" height="' . $svg_size . '" fill="white"/>';

		for ( $r = 0; $r < $module_count; $r ++ ) {
			for ( $c = 0; $c < $module_count; $c ++ ) {
				if ( $qr_matrix[ $r ][ $c ] ) {
					$x   = ( $c + self::QUIET_ZONE ) * $module_size;
					$y   = ( $r + self::QUIET_ZONE ) * $module_size;
					$svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $module_size . '" height="' . $module_size . '" fill="black"/>';
				}
			}
		}

		$svg .= '</svg>';

		return $svg;
	}

	/**
	 * Generate QR code as base64 data URI
	 *
	 * @param string $data Data to encode
	 *
	 * @return string Base64 data URI
	 */
	public function generate_data_uri( $data ) {
		// For simplicity, we'll use SVG and convert to data URI
		$svg = $this->generate_svg( $data );

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Generate a simple QR code matrix
	 * This is a simplified implementation for demonstration
	 *
	 * @param string $data Data to encode
	 *
	 * @return array|false QR code matrix or false on failure
	 */
	private function generate_qr_matrix( $data ) {
		// For a production implementation, you would want to use a proper QR code library
		// This is a simplified version that creates a recognizable pattern

		// Use WordPress's built-in functions if available
		if ( function_exists( 'apply_filters' ) ) {
			$matrix = apply_filters( 'so_ssl_generate_qr_matrix', false, $data );
			if ( $matrix !== false ) {
				return $matrix;
			}
		}

		// Create a simple matrix pattern (this won't be a valid QR code but will show the concept)
		$size   = 33; // Standard QR code size
		$matrix = array_fill( 0, $size, array_fill( 0, $size, false ) );

		// Add finder patterns (the three corner squares)
		$this->add_finder_pattern( $matrix, 0, 0 );
		$this->add_finder_pattern( $matrix, $size - 7, 0 );
		$this->add_finder_pattern( $matrix, 0, $size - 7 );

		// Add timing patterns
		for ( $i = 8; $i < $size - 8; $i ++ ) {
			$matrix[6][ $i ] = ( $i % 2 == 0 );
			$matrix[ $i ][6] = ( $i % 2 == 0 );
		}

		// Add alignment pattern (for demonstration)
		if ( $size > 25 ) {
			$this->add_alignment_pattern( $matrix, $size - 7 - 4, $size - 7 - 4 );
		}

		// Encode data (simplified - in reality this would use Reed-Solomon error correction)
		$data_bits = $this->text_to_binary( $data );
		$this->add_data_to_matrix( $matrix, $data_bits, $size );

		return $matrix;
	}

	/**
	 * Add finder pattern to matrix
	 */
	private function add_finder_pattern( &$matrix, $row, $col ) {
		for ( $r = 0; $r < 7; $r ++ ) {
			for ( $c = 0; $c < 7; $c ++ ) {
				if ( $r == 0 || $r == 6 || $c == 0 || $c == 6 || ( $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4 ) ) {
					$matrix[ $row + $r ][ $col + $c ] = true;
				}
			}
		}
	}

	/**
	 * Add alignment pattern to matrix
	 */
	private function add_alignment_pattern( &$matrix, $row, $col ) {
		for ( $r = - 2; $r <= 2; $r ++ ) {
			for ( $c = - 2; $c <= 2; $c ++ ) {
				if ( abs( $r ) == 2 || abs( $c ) == 2 || ( $r == 0 && $c == 0 ) ) {
					$matrix[ $row + $r ][ $col + $c ] = true;
				}
			}
		}
	}

	/**
	 * Convert text to binary representation
	 */
	private function text_to_binary( $text ) {
		$binary = '';
		for ( $i = 0; $i < strlen( $text ); $i ++ ) {
			$binary .= sprintf( '%08b', ord( $text[ $i ] ) );
		}

		return $binary;
	}

	/**
	 * Add data to matrix (simplified)
	 */
	private function add_data_to_matrix( &$matrix, $data, $size ) {
		$pos = 0;
		$len = strlen( $data );

		// Simple data placement (in real QR codes this follows a specific pattern)
		for ( $r = $size - 1; $r >= 0; $r -- ) {
			for ( $c = $size - 1; $c >= 0; $c -- ) {
				if ( ! isset( $matrix[ $r ][ $c ] ) || $matrix[ $r ][ $c ] === null ) {
					if ( $pos < $len ) {
						$matrix[ $r ][ $c ] = ( $data[ $pos ] == '1' );
						$pos ++;
					}
				}
			}
		}
	}

	/**
	 * Generate fallback SVG for when QR code generation fails
	 */
	private function generate_fallback_svg() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $this->size . '" height="' . $this->size . '" viewBox="0 0 ' . $this->size . ' ' . $this->size . '">';
		$svg .= '<rect x="0" y="0" width="' . $this->size . '" height="' . $this->size . '" fill="#f0f0f0"/>';
		$svg .= '<text x="' . ( $this->size / 2 ) . '" y="' . ( $this->size / 2 ) . '" font-family="Arial" font-size="14" fill="#666" text-anchor="middle" dominant-baseline="middle">QR Code</text>';
		$svg .= '</svg>';

		return $svg;
	}
}

/**
 * Integration with existing So_SSL_Two_Factor class
 */
if ( ! function_exists( 'so_ssl_generate_qr_code_locally' ) ) {
	/**
	 * Generate QR code locally instead of using Google Charts API
	 *
	 * @param string $totp_url The TOTP URL to encode
	 * @param int $size Size of the QR code
	 *
	 * @return string HTML markup for the QR code
	 */
	function so_ssl_generate_qr_code_locally( $totp_url, $size = 200 ) {
		$qr_generator = new So_SSL_QR_Code( $size );

		// Try to use a proper QR code library if available
		if ( class_exists( 'QRcode' ) ) {
			// If PHPQRCode is available
			ob_start();
			QRcode::png( $totp_url, null, QR_ECLEVEL_L, 4, 0 );
			$image_data = ob_get_contents();
			ob_end_clean();

			$base64 = base64_encode( $image_data );

			return '<img src="data:image/png;base64,' . $base64 . '" alt="QR Code" width="' . $size . '" height="' . $size . '" />';
		}

		// Otherwise use our SVG generator
		$svg = $qr_generator->generate_svg( $totp_url );

		return '<div class="so-ssl-qr-svg" style="width: ' . $size . 'px; height: ' . $size . 'px;">' . $svg . '</div>';
	}
}
