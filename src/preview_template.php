<html>
<head>
    <?php
    if ($name) {
        echo '<title>'.htmlspecialchars($name).'</title>';
    }
    ?>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            line-height: 21px;
        }

        h1 small {
            font-weight: normal;
            font-size: 18px;
            color: #777;
        }

        .emoji-wrap {
            display: inline-block;
            min-width: 170px;
            padding-right: 30px;
            overflow: hidden;
        }

        .emoji-img-wrap {
            display: inline-block;
            text-align: center;
            min-width: <?php echo $maxSize['w']; ?>px;
        }

        .emoji {
            vertical-align: text-bottom;
        }

        .alias {
            float: right;
            opacity: .5;
        }
    </style>
</head>
<body>
<?php
if ($name) {
    echo '<h1>'.
        htmlspecialchars($name).
        ' <small>'.number_format(count($emoji), 0).' emoji</small>'.
        '</h1>';
}
?>
    <div class="emoji-list">
        <?php
        $size = 1;
        $size2x = $size * 2;
        if ($size > 1) {
            $format = str_replace('@2x', "@{$size2x}x", $format);
        }
        foreach ($emoji as $emoji_name => $filename) {
            $src = $filename;
            $dir = '';
            $ext = '.'.pathinfo($filename, PATHINFO_EXTENSION);
            $basename = basename($filename, $ext);
            if ($size > 1) {
                $src = "$basename@{$size}x{$ext}";
            }

            $img = str_replace(
                array('%1$s', '%2$s', '{src}', '{name}', '{dir}', '{filename}', '{basename}', '{ext}'),
                array($src, $emoji_name, $src, $emoji_name, $dir, $filename, $basename, $ext),
                $format
            );

            $alias = array_search($emoji_name, $aliases);

            echo '<span class="emoji-wrap emoji-wrap-'.$size.'x">'.
                '<span class="emoji-img-wrap">'.$img.'</span>'.
                " :$emoji_name:".
                ($alias ? '<span class="alias">'.$alias.'</span>' : '').
                "</span>\n";
        }
        ?>
    </div>
</body>
</html>