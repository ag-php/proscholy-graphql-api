<?php

namespace App;

use Illuminate\Database\Eloquent\Model; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

use Spatie\PdfToImage\Pdf;

use Spatie\PdfToText\Pdf as PdfToText;

/**
 * App\File
 *
 * @property string|null $filename
 * @property int|null $song_lyric_id
 * @property int|null $author_id
 * @property int|null $licence_type
 * @property string|null $licence_content
 * @property string|null $decription
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\File whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\File whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\File whereDecription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\File whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\File whereLicenceContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\File whereLicenceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\File whereSongLyricId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\File whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int $id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\File whereId($value)
 */
class File extends Model
{
    protected $fillable = ['filename', 'type', 'description', 'path', 'name', 'has_anonymous_author', 'downloads'];

    // See App/Listeners/FileDeleting where the deleting actually happens
    protected $dispatchesEvents = [
        'deleting' => \App\Events\FileDeleting::class,
    ];

    public $type_string
        = [
            0 => 'soubor',
            1 => 'text',
            2 => 'text/akordy',
            3 => 'noty',
            4 => 'audio nahrávka'
        ];

    public function getPublicName()
    {
        if ($this->name == null) {
            return $this->filename;
        }

        return "$this->name ($this->filename)";
    }

    public function getPublicNameAttribute()
    {
        return $this->getPublicName();
    }

    public function getDownloadUrlAttribute()
    {
        return route('download.file', [
            'file' => $this->id,
            'filename' => $this->filename
        ]);
    }

    public function getThumbnailUrlAttribute()
    {
        return route('file.thumbnail', [
            'file' => $this->id,
        ]);
    }

    public function getTypeString()
    {
        return $this->type_string[$this->type];
    }

    public function getTypeStringAttribute() 
    {
        return $this->getTypeString();
    }

    protected static function getThubmnailsFolder()
    {
        $relative = '/public_files/thumbnails';

        // first create if doesn't exist
        if (!file_exists(Storage::path($relative)))
            mkdir(Storage::path($relative));

        return $relative;
    }

    public function canHaveThumbnail()
    {
        return pathinfo($this->path, PATHINFO_EXTENSION) == "pdf";
    }

    public function getThumbnailPath()
    {
        if (!$this->canHaveThumbnail())
            return;

        // get the path of a thumbnail file
        $relative = self::getThubmnailsFolder().
            '/'.
            pathinfo($this->path, PATHINFO_FILENAME).
            '.jpg';

        // if already exists, do not create new one
        if (file_exists(Storage::path($relative))) {
            return $relative;
        }
        
        // create a new thumbnail file
        $pdf = new Pdf(Storage::path($this->path));
        $pdf->setCompressionQuality(20)
            ->saveImage(Storage::path($relative));

        \Log::info("thumbnail $relative created");

        return $relative;
    }

    public function scopeRestricted($query)
    {
        if (Auth::user()->hasRole('autor')) {
            return $query->whereHas('author', function($q) {
                $q->whereIn('authors.id', Auth::user()->getAssignedAuthorIds());
            })->orWhereHas('song_lyric', function($q) {
                $q->restricted();
            });
        } else {
            return $query;
        }
    }

    public function scopeAudio($query)
    {
        return $query->where('type', 4);
    }

    public function scopeOthers($query)
    {
        return $query->where('type', 0)->orWhere('type', 1)->orWhere('type', 2);
    }

    public function scopeTodo($query)
    {
        return $query->where('author_id', null)->where('has_anonymous_author', 0)
            ->orWhere('song_lyric_id', null);
    }

    public function authors()
    {
        return $this->belongsToMany(Author::class);
    }

    public function song_lyric()
    {
        return $this->belongsTo(SongLyric::class);
    }

    public function getPdfText()
    {
        if (pathinfo($this->path, PATHINFO_EXTENSION) !== "pdf")
            return "";

        $text = PdfToText::getText(Storage::path($this->path));
        $text = str_replace('-', ' ', str_slug($text));

        return $text;
    }
}
