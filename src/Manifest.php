<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Emoji;

class Manifest {
    /// Constants ///

    const DEFAULT_FORMAT = '<img class="emoji" src="{src}" title="{name}" alt="{name}" />';
    const MAX_EDITOR_COUNT = 16;
    const EDITOR_DENOMINATOR = 4;

    /// Properties ///

    /**
     * @var string The format of the manifest.
     */
    protected $format = 'json';

    /**
     * @var string The path to the emoji folder.
     */
    protected $imagePath;

    /**
     * @var string The path to the manifest file.
     */
    protected $manifestPath;


    /// Methods ///

    public function build() {
        // Read in the current manifest.
        $currentManifest = $this->load();
        $currentFiles = array_flip(static::val('emoji', $currentManifest, []));

        // Figure out all of the emoji.
        $parsed = $this->parseDirectory($this->getImagePath());
        $emoji = [];
        $defaultArchive = $this->getDefaultArchive();
        $archive = [];
        foreach ($parsed['emoji'] as $name => $row) {
            $filename = $row['filename'];
            if (isset($currentFiles[$filename])) {
                $name = $currentFiles[$filename];
            }

            if (isset($defaultArchive[$name])) {
                $archive[$name] = $filename;
            } else {
                $emoji[$name] = $filename;
            }
        }
        ksort($emoji);

        // Figure out all of the aliases.
        $currentAliases = array_replace($this->getDefaultAliases(), static::val('aliases', $currentManifest, []));
        $aliases = [];
        foreach ($currentAliases as $alias => $name) {
            if (isset($emoji[$name])) {
                $aliases[$alias] = $name;
            }
        }

        // Add any remaining archived emoji.
        foreach ($defaultArchive as $alias => $name) {
            if (isset($emoji[$name])) {
                $archive[$alias] = $emoji[$name];
            }
        }
        ksort($archive);

        // Figure out the editor.
        $editor = $this->guessEditorList(
            static::val('editor', $currentManifest, []),
            $parsed
        );

        // Build the manifest.
        $manifest = [
            'name' => '',
            'author' => '',
            'description' => '',
            'format' => self::DEFAULT_FORMAT,
            'emoji' => $emoji,
            'aliases' => $aliases,
            'editor' => $editor,
            'archive' => $archive,
            'minSize' => static::val('minSize', $parsed, static::val('maxSize', $currentManifest, [])),
            'maxSize' => static::val('maxSize', $parsed, static::val('maxSize', $currentManifest, [])),
            'sizes' => $parsed['sizes']
        ];

        // Add the rest of the current manifest back to the end of the manifest.
        unset($currentManifest['emoji'], $currentManifest['aliases'], $currentManifest['archive']);
        $manifest = array_replace($manifest, $currentManifest);

        return $manifest;
    }

    public function buildPreview($manifest) {
        extract($manifest);

        ob_start();
        require __DIR__.'/preview_template.php';
        $previewHtml = ob_get_clean();

        file_put_contents($this->getImagePath().'/preview.html', $previewHtml);
    }

    public function getDefaultAliases() {
        $defaults = json_decode(file_get_contents(__DIR__.'/defaults.json'), true);
        return $defaults['aliases'];
    }

    public function getDefaultArchive() {
        $defaults = json_decode(file_get_contents(__DIR__.'/defaults.json'), true);
        return $defaults['archive'];
    }

    public function getDefaultEditorList() {
        $defaults = json_decode(file_get_contents(__DIR__.'/defaults.json'), true);
        return $defaults['editor'];
    }

    public function guessEditorList($current, $parsed) {
        // Build a list of candidates.
        $candidates = array_replace(
            $current,
            $this->getDefaultEditorList()
        );

        $emoji = $parsed['emoji'];
        $result = [];

        foreach ($candidates as $alias => $name) {
            // Skip non-existent emoji.
            if (!isset($emoji[$name])) {
                continue;
            }
            $row = $emoji[$name];

            // Keep current emoji.
            if (isset($current[$alias])) {
                $result[$alias] = $name;
            }

            // Skip emoji that are too wide.
            if ($row['w'] > $row['h']) {
                continue;
            }

            $result[$alias] = $name;
        }

        if (count($result) > self::MAX_EDITOR_COUNT) {
            $count = self::MAX_EDITOR_COUNT;
        } else {
            $count = count($result) - (count($result) % self::EDITOR_DENOMINATOR);
        }
        $result = array_slice($result, 0, $count, true);
        return $result;
    }

    public function emojiNameFromPath($path) {
        $basename = basename($path);
        $result = preg_replace('`^(.+?)(@[0-9\.]+x)?\.[a-z]+$`i', '$1', $basename); // strip @2x and ext
        return $result;
    }

    /**
     * @return string
     */
    public function getImagePath() {
        return $this->imagePath;
    }

    /**
     * Set the image path.
     *
     * @param string $imagePath The new path to the images.
     * @throws \Exception Throws an exception when {@link $imagePath} is not valid.
     */
    public function setImagePath($imagePath) {
        if ($imagePath) {
            $path = realpath($imagePath);

            if ($path === false) {
                throw new \Exception("Image path $imagePath cannot be read or does not exist.", 500);
            }
            if (is_file($path)) {
                throw new \Exception("Image path $imagePath is not a directory.", 500);
            }
            $imagePath = $path;
        }

        $this->imagePath = $imagePath;
    }

    /**
     * @return string
     */
    public function getManifestPath() {
        if (!$this->manifestPath) {
            $result = $this->getImagePath().'/manifest.'.$this->getFormat();
            return $result;
        } else {
            return $this->manifestPath;
        }
    }

    /**
     * @param string $manifestPath
     */
    public function setManifestPath($manifestPath) {
        $this->manifestPath = $manifestPath;
    }

    protected function parseDirectory($path) {
        $glob = rtrim($path, '/').'/*.*';
        $emoji = [];
        $minWidth = PHP_INT_MAX;
        $minHeight = PHP_INT_MAX;
        $maxWidth = 0;
        $maxHeight = 0;

        $paths = glob($glob);
        foreach ($paths as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $basename = basename($path);
            if (!in_array($ext, ['gif', 'jpg', 'jpeg', 'png', 'svg'])) {
                continue;
            }
            if (strcasecmp(basename($path, $ext), 'icon.') === 0 || stripos($basename, '-icon') !== false) {
                continue;
            }

            $emojiName = $this->emojiNameFromPath($basename);
            if (preg_match('`@([0-9\.]+)x`', $basename, $m)) {
                $size = (float)$m[1];
            } else {
                $size = 1;
            }

            if ($ext !== 'svg') {
                list($w, $h) = getimagesize($path);
            } else {
                $w = 20;
                $h = 20;
            }

            if (isset($emoji[$emojiName])) {
                $row = $emoji[$emojiName];
            } else {
                $row = ['filename' => $basename, 'name' => $emojiName];
            }

            if ($size === 1) {
                $row['w'] = $w;
                $row['h'] = $h;
                $row['filename'] = $basename;

                $minWidth = min($minWidth, $w);
                $minHeight = min($minHeight, $h);

                $maxWidth = max($maxWidth, $w);
                $maxHeight = max($maxHeight, $h);
            }

            $row['sizes']['@'.$size] = ['w' => $w, 'h' => $h, 'mult' => $size];
            $emoji[$emojiName] = $row;
        }

        $first = reset($emoji);
        $sizes = array_column($first['sizes'], 'mult');

        return [
            'emoji' => $emoji,
            'minSize' => ['w' => $minWidth, 'h' => $minHeight],
            'maxSize' => ['w' => $maxWidth, 'h' => $maxHeight],
            'sizes' => $sizes
            ];
    }

    /**
     * Loads the current manifest.
     *
     * @return array The current manifest.
     * @throws \Exception
     */
    public function load() {
        if (!file_exists($this->getManifestPath())) {
            return [];
        }

        switch ($this->getFormat()) {
            case 'json':
                $data = json_encode(file_get_contents($this->getManifestPath()), true);
                break;
            case 'php':
                $data = require $this->getManifestPath();
                break;
            default:
                throw new \Exception("Invalid manifest format.", 500);
        }
        if (!is_array($data)) {
            $data = [];
        }
        return $data;
    }

    /**
     * Save the current emoji manifest.
     *
     * @param array $data The emoji manifest to save.
     * @throws \Exception Throws an exception if the save format is invalid.
     */
    public function save($data) {
        switch ($this->getFormat()) {
            case 'json':
                $str = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;
            case 'php':
                $str = '<?php return '.var_export($data, true).";\n";
                $str = str_replace(" \n", "\n", $str);
                break;
            default:
                throw new \Exception("Invalid manifest format.", 500);
        }
        $r = file_put_contents($this->getManifestPath(), $str);
    }

    /**
     * Safely get a value out of an array.
     *
     * This function uses optimizations found in the [facebook libphputil library](https://github.com/facebook/libphutil).
     *
     * @param string|int $key The array key.
     * @param array $array The array to get the value from.
     * @param mixed $default The default value to return if the key doesn't exist.
     * @return mixed The item from the array or `$default` if the array key doesn't exist.
     */
    public static function val($key, array $array, $default = null) {
        // isset() is a micro-optimization - it is fast but fails for null values.
        if (isset($array[$key])) {
            return $array[$key];
        }

        // Comparing $default is also a micro-optimization.
        if ($default === null || array_key_exists($key, $array)) {
            return null;
        }

        return $default;
    }

    /**
     * @return string
     */
    public function getFormat() {
        return $this->format;
    }

    /**
     * @param string $format
     */
    public function setFormat($format) {
        if (!in_array($format, ['json', 'php'])) {
            throw new \Exception("Invalid format: $format.", 500);
        }
        $this->format = $format;
        return $this;
    }
}
 