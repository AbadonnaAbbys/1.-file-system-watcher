# File System Watcher

A Laravel-based application that monitors file system changes and performs various actions based on the file type and event.

## Features

- **Real-time File Monitoring**: Watches specified directories for file changes
- **Event-Based Processing**: Triggers appropriate actions based on file events (created, modified, deleted)
- **Image Optimization**: Automatically optimizes image files
- **JSON Processing**: Processes JSON files when created or modified
- **Text File Processing**: Handles text files as needed
- **ZIP Extraction**: Automatically extracts ZIP files
- **Deleted Image Replacement**: Replaces deleted images with random memes

## System Requirements

- PHP 8.3+
- Laravel 10.x
- Composer

## Getting Started

### Installation

1. Clone the repository:
   ```
   git clone [repository-url]
   cd file-system-watcher
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Set up environment:
   ```
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure your environment variables in `.env` file:
   ```
   WATCHER_DIRECTORY=/path/to/watched
   IMAGE_JPEG_QUALITY=80
   IMAGE_PNG_COMPRESSION=9
   IMAGE_WEBP_QUALITY=80
   MEME_API_URL=https://meme-api.com/gimme
   ```

### Running the Application

Start the file watcher:


## Configuration Options

| Environment Variable | Description | Default |
|---------------------|-------------|---------|
| `WATCHER_DIRECTORY` | Directory to watch for changes | `/app/watched` |
| `IMAGE_JPEG_QUALITY` | JPEG compression quality | 80 |
| `IMAGE_PNG_COMPRESSION` | PNG compression level | 9 |
| `IMAGE_WEBP_QUALITY` | WebP compression quality | 80 |
| `MEME_API_URL` | API for fetching memes | https://meme-api.com/gimme |
| `ZIP_EXTRACT_PATH` | Path to extract ZIP files | storage/extracted |

## Event Listeners

The application includes several event listeners:

- `LogFileChanged`: Logs all file change events
- `OptimizeImageFile`: Optimizes image files
- `ProcessJsonFile`: Processes JSON files
- `ProcessTextFile`: Handles text files
- `ExtractZipFile`: Extracts ZIP archives
- `ReplaceDeletedFileWithMeme`: Replaces deleted images with memes

## Development Challenges and Solutions

### Challenges Encountered

1. **External API Reliability**
    - The meme API (https://meme-api.com/gimme) occasionally returned 5xx errors, causing the file replacement functionality to fail.
    - This resulted in "Failed to fetch meme" errors in the log and missing replacement files.

2. **Handling Multiple File Types**
    - Different file types required different processing approaches, making the codebase potentially complex.
    - Initial approach led to tightly coupled code that was difficult to maintain.

3. **Race Conditions**
    - Files being processed simultaneously could cause locks or corrupted data.
    - Needed to ensure atomic operations when multiple files changed at once.

4. **Error Recovery**
    - System needed to gracefully handle errors without stopping the entire watching process.
    - External service failures shouldn't affect other file processing tasks.

### Preventing Infinite Processing Loops

One of the most significant challenges in developing the FileSystemWatcher was preventing infinite processing loops. These loops could occur when:

1. **File Processing Triggers More Changes**: When processing a file (like optimizing an image) creates another file change event, which then triggers the same processor again.

2. **Replacement Files Trigger Events**: When a replaced or generated file (like a meme replacing a deleted image) triggers new file change events.

3. **Recursive Processing**: When processing extracted ZIP files creates new events for each extracted file.

#### Solution: Event Fingerprinting and Processing Lock

To solve this problem, we implemented a multi-layered approach:

1. **Event Fingerprinting**:
    - Each file change event is assigned a unique fingerprint based on the file path, modification time, and action type.
    - Before processing an event, the system checks if this fingerprint was recently processed.
    - This prevents the same physical file change from being processed multiple times.

2. **Processing Lock Mechanism**:
    - A temporary flag file is created during processing with the format `.processing_{hash}`.
    - The watcher ignores any files that have an active processing lock.
    - Locks automatically expire after a configurable timeout period (default: 30 seconds).

3. **Event Debouncing**:
    - Multiple rapid changes to the same file are consolidated into a single event.
    - This is particularly useful for applications that save files incrementally.

4. **Intelligent Path Exclusions**:
    - The system maintains a registry of paths that are currently being processed.
    - Any changes detected in these paths during processing are ignored.
    - Paths are automatically released from this registry when processing completes.

Example implementation:

```php
// Before processing a file
if ($this->isBeingProcessed($path)) {
    return; // Skip processing to avoid loops
}

// Register file as being processed
$this->markAsProcessing($path);

try {
    // Process the file
    $this->processFile($path);
} finally {
    // Always release the processing lock, even if an exception occurs
    $this->releaseProcessing($path);
}
```
