# WP-Migrate Architecture Documentation

## Overview

WP-Migrate is a WordPress plugin designed for secure, resumable migrations between production and staging environments. This document outlines the architectural decisions, implementation choices, and design patterns used throughout the project.

## 🏗️ Design Principles

### DRY (Don't Repeat Yourself)
- **Centralized Authentication**: Single `HmacAuth` class handles all HMAC verification
- **Unified Error Handling**: `error_to_response()` method provides consistent error formatting
- **Service Registration**: All services implement `Registrable` interface for uniform initialization

### YAGNI (You Aren't Gonna Need It)
- **Minimal Dependencies**: Only essential WordPress functions, no external libraries
- **Simple State Management**: WordPress options-based storage, no complex databases
- **Basic Logging**: JSONL format without advanced log aggregation features

### Security First
- **HMAC Signing**: All API requests cryptographically verified
- **TLS Enforcement**: HTTPS required with proxy header support
- **Input Validation**: REST args validation and path sanitization
- **Nonce Protection**: Replay attack prevention with time-based expiration

## 🏛️ Architecture Patterns

### Service-Oriented Architecture
Each major feature is encapsulated in its own service class:

```php
interface Registrable {
    public function register(): void;
}

final class Plugin {
    private array $services = [];
    
    private function register_services(): void {
        $this->services = [
            new Frontend(),
            new SettingsPage(),
            new Api( $auth ),
        ];
        
        foreach ( $this->services as $service ) {
            $service->register();
        }
    }
}
```

**Benefits:**
- Clear separation of concerns
- Easy to test individual components
- Simple to add new features
- Consistent initialization pattern

### Dependency Injection
Services receive their dependencies through constructors:

```php
final class Api implements Registrable {
    private HmacAuth $auth;
    private ChunkStore $chunks;
    private JobManager $jobs;

    public function __construct( HmacAuth $auth ) {
        $this->auth = $auth;
        $this->chunks = new ChunkStore();
        $this->jobs = new JobManager( new StateStore() );
    }
}
```

**Benefits:**
- Testable components
- Loose coupling
- Clear dependencies
- Easy to mock for testing

### Factory Pattern for Settings
Settings provider is injected as a callable:

```php
$auth = new HmacAuth( function () {
    $opts = \get_option( SettingsPage::OPTION, [] );
    return [
        'shared_key' => isset( $opts['shared_key'] ) ? (string) $opts['shared_key'] : '',
        'peer_url'   => isset( $opts['peer_url'] ) ? (string) $opts['peer_url'] : '',
    ];
} );
```

**Benefits:**
- Lazy loading of settings
- Easy to override for testing
- No hard dependencies on WordPress functions in core classes

## 🔐 Security Architecture

### HMAC Authentication Flow
1. **Request Validation**: Check timestamp, nonce, and signature
2. **TLS Verification**: Ensure HTTPS (with proxy header support)
3. **Nonce Replay Protection**: Cache used nonces for 1 hour
4. **Signature Verification**: HMAC-SHA256 with shared secret

```php
private function verify_request( WP_REST_Request $request ) {
    // 1. Check shared key configuration
    if ( empty( $key ) ) {
        return new WP_Error( 'EAUTH', 'Shared key is not configured' );
    }

    // 2. Verify TLS (including proxy scenarios)
    if ( ! $this->is_secure_request() ) {
        return new WP_Error( 'EUPGRADE_REQUIRED', 'TLS required' );
    }

    // 3. Validate timestamp (5-minute skew window)
    if ( \abs( $nowMs - $ts ) > self::MAX_SKEW_MS ) {
        return new WP_Error( 'ETS_SKEW', 'Timestamp skew too large' );
    }

    // 4. Check nonce replay
    if ( $this->is_nonce_used( $nonce ) ) {
        return new WP_Error( 'ENONCE_REPLAY', 'Nonce has already been used' );
    }

    // 5. Verify HMAC signature
    $payload = $ts . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . $bodyHash;
    $calc = base64_encode( \hash_hmac( 'sha256', $payload, $key, true ) );
    if ( ! \hash_equals( $calc, $sig ) ) {
        return new WP_Error( 'EAUTH', 'Invalid signature' );
    }
}
```

### File Security
- **Path Sanitization**: `sanitize_file_name()` for all file operations
- **Directory Traversal Protection**: Explicit checks for `..` in path components
- **Size Limits**: 64MB chunk size limit to prevent memory exhaustion
- **Hash Validation**: SHA256 verification for all uploaded chunks

## 📊 State Management

### Job Lifecycle
Jobs progress through defined states with idempotent operations:

```
created → preflight_ok → files_pass1 → db_exported → 
db_uploaded → db_imported → url_replaced → files_pass2 → 
finalized → done
```

### Storage Strategy
- **WordPress Options**: Simple, reliable storage for job metadata
- **File System**: Chunks and logs stored in `wp-uploads/` directory
- **Transients**: Nonce storage with automatic expiration

```php
final class StateStore {
    private const OPTION_PREFIX = 'mig_job_';
    
    public function put_job( string $jobId, array $job ): void {
        \update_option( self::OPTION_PREFIX . $jobId, $job, false );
    }
}
```

**Benefits:**
- Leverages WordPress's built-in caching
- Automatic cleanup with WordPress maintenance
- No additional database tables required
- Familiar to WordPress developers

## 🚀 Performance Considerations

### Lazy Loading
- Services only instantiated when needed
- File operations deferred until required
- Settings loaded on-demand

### Efficient File Handling
- **Chunked Uploads**: Large files split into manageable pieces
- **Resume Support**: Automatic detection of existing chunks
- **Streaming**: Direct file operations without memory buffering

### Caching Strategy
- **Nonce Cache**: 1-hour TTL for replay protection
- **Job State**: WordPress options with autoload disabled
- **Capability Detection**: System binary checks cached per request

## 🔧 Error Handling

### Consistent Error Format
All errors follow the same structure:

```php
{
    "ok": false,
    "code": "EAUTH",
    "message": "Authentication failed",
    "status": 401
}
```

### Error Categories
- **EAUTH**: Authentication and authorization failures
- **ETS_SKEW**: Timestamp validation errors
- **ENONCE_REPLAY**: Nonce reuse attempts
- **EPREFLIGHT_FAILED**: System requirement failures
- **EBAD_REQUEST**: Invalid input parameters
- **EBAD_CHUNK**: File upload validation failures

### Graceful Degradation
- **Capability Detection**: Features disabled if system requirements not met
- **Fallback Mechanisms**: Alternative approaches when preferred methods unavailable
- **Informative Messages**: Clear guidance on resolving issues

## 🧪 Testing Strategy

### Current Status
- **Manual Testing**: REST endpoint validation and security verification
- **Planned**: Unit and integration test suite
- **Static Analysis**: PSR-4 autoloading compliance verified
- **WordPress Standards**: Function usage and security practices

## 📈 Scalability Considerations

### Horizontal Scaling
- **Stateless Design**: No server-side session storage
- **Shared Storage**: WordPress options work across multiple instances
- **File Storage**: Standard file system operations

### Performance Optimization
- **Chunk Size Tuning**: Configurable chunk sizes for different environments
- **Batch Operations**: Multiple operations in single requests
- **Async Processing**: Background job processing for long-running operations

## 🔮 Future Enhancements

### Planned Improvements
- **Database Engine**: MySQL export/import with URL rewriting
- **Rollback System**: Automated restoration from snapshots
- **WP-CLI Integration**: Command-line interface for migrations
- **Multi-site Support**: Network-wide migration capabilities

### Architectural Evolution
- **Event System**: Hook-based architecture for extensibility
- **Plugin API**: Third-party integration capabilities
- **Advanced Logging**: Structured logging with external aggregation
- **Performance Monitoring**: Metrics collection and analysis

## 📚 Best Practices

### WordPress Development
- Follow WordPress coding standards
- Use WordPress functions when available
- Implement proper capability checks
- Sanitize inputs and escape outputs

### Security
- Validate all external inputs
- Use cryptographic functions for sensitive operations
- Implement proper error handling
- Follow principle of least privilege

### Performance
- Minimize database queries
- Use appropriate caching strategies
- Optimize file operations
- Monitor resource usage

This architecture provides a solid foundation for secure, reliable WordPress migrations while maintaining simplicity and extensibility.
