<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Traits\Lockable;
use ScoutElastic\Searchable;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// use App\Helpers\Chord;
// use App\Helpers\ChordSign;
// use App\Helpers\ChordQueue;
// use App\Helpers\SongPart;
use App\Helpers\SongLyricHelper;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Venturecraft\Revisionable\RevisionableTrait;

/**
 * App\SongLyric
 *
 * @property int                                                           $id
 * @property string|null                                                   $name
 * @property string|null                                                   $description
 * @property string|null                                                   $lyrics
 * @property int|null                                                      $is_opensong
 * @property int|null                                                      $lang_id
 * @property int|null                                                      $song_id
 * @property int|null                                                      $licence_type
 * @property string|null                                                   $licence_content
 * @property \Carbon\Carbon|null                                           $created_at
 * @property \Carbon\Carbon|null                                           $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Author[]   $authors
 * @property-read \App\Song|null                                           $song
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereIsAuthorized($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereIsOpensong($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereIsOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereLangId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereLicenceContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereLicenceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereLyrics($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int|null                                                      $visits
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereVisits($value)
 * @property string                                                        $lang
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\External[] $externals
 * @method static \Illuminate\Database\Eloquent\Builder|\App\SongLyric whereLang($value)
 */
class SongLyric extends Model
{
    // ElasticSearch Searchable Trait used for full-text searching
    use Searchable,
        // Lockable Trait for enabling to "lock" the model while editing
        Lockable,
        SoftDeletes,
        RevisionableTrait;
    protected $revisionCleanup = true;
    protected $historyLimit = 200;
    protected $revisionCreationsEnabled = true;
    protected $dontKeepRevisionOf = ['has_chords', 'creating_at', 'created_at', 'visits', 'updating_at', 'updating_user_id', 'bible_refs_osis'];

    protected $indexConfigurator = SongLyricIndexConfigurator::class;

    // the Elasticsearch metadata for attributes retrieved by toSearchableArray()
    protected $mapping = [
        'properties' => [
            'name' => [
                'type' => 'text',
                'analyzer' => 'name_analyzer',
            ],
            'lyrics' => [
                'type' => 'text',
                'analyzer' => 'czech_analyzer'
            ],
            'authors' => [
                'type' => 'text',
                'analyzer' => 'name_analyzer'
            ],
            'songook_records' => [
                'type' => 'nested',
                'properties' => [
                    'songbook_id' => [
                        'type' => 'keyword'
                    ],
                    'songbook_number' => [
                        'type' => 'keyword'
                    ],
                    'songbook_number_integer' => [
                        'type' => 'integer'
                    ],
                    'songbook_full_number' => [
                        'type' => 'keyword'
                    ]
                ]
            ],
            'tag_ids' => [
                'type' => 'keyword'
            ],
            'lang' => [
                'type' => 'keyword'
            ],
            // the 'text' type cannot be used for sorting, this is why a copy of name is included
            'name_keyword' => [
                'type' => 'keyword'
            ],
            'is_arrangement' => [
                'type' => 'boolean'
            ],
            'tag_instrumentation_ids' => [
                'type' => 'keyword'
            ],
            'tag_period_ids' => [
                'type' => 'keyword'
            ],
            'song_number' => [
                'type' => 'keyword'
            ],
            'song_number_integer' => [
                'type' => 'integer'
            ],
            'only_regenschori' => [
                'type' => 'boolean'
            ],
            'has_media_files_externals' => [
                'type' => 'boolean'
            ],
            'has_score_files_externals' => [
                'type' => 'boolean'
            ],
            'has_lyrics' => [
                'type' => 'boolean'
            ],
            'has_chords' => [
                'type' => 'boolean'
            ]
        ]
    ];

    protected $dispatchesEvents = [
        'saved' => \App\Events\SongLyricSaved::class,
        'updated' => \App\Events\SongLyricSaved::class,
        'created' => \App\Events\SongLyricCreated::class,
    ];

    protected $fillable
    = [
        'name',
        'song_id',
        'lyrics',
        'id',
        // 'is_original',
        // 'is_authorized',
        'type',
        'lang',
        'creating_at',
        'has_anonymous_author',
        'has_chords',
        'is_published',
        'is_approved_by_author',
        'user_creator_id',
        'licence_type',
        'only_regenschori',
        'capo',
        'visits',
        'liturgy_approval_status',
        'arrangement_of',
        'lilypond',
        'lilypond_svg',
        'song_number',
        'bible_refs_src',
        'bible_refs_osis',
        'secondary_name_1',
        'secondary_name_2',
        'licence_type_cc',
        'admin_note'
    ];

    private static $lang_string_values = [
        'cs' => 'čeština',
        'sk' => 'slovenština',
        'en' => 'angličtina',
        'la' => 'latina',
        'pl' => 'polština',
        'de' => 'němčina',
        'fr' => 'francouzština',
        'es' => 'španělština',
        'it' => 'italština',
        'sv' => 'svahilština',
        'he' => 'hebrejština',
        'cr' => 'chorvatština',
        'cu' => 'staroslověnština',
        // 'wtf' => 'jazyk domorodých kmenů jižní Oceánie',
        'mixed' => 'vícejazyčná píseň'
    ];

    private static $liturgy_approval_status_string_values = [
        0 => 'bez vyjádření ČBK',
        1 => 'schváleno ČBK pro liturgii',
        // 2 => 'schváleno ČBK pro dětskou mši',
        // 3 => 'neschváleno ČBK pro liturgii',
    ];

    private static $licence_type_cc_string_values = [
        'UNSET' => 'neuvedeno',
        'BY' => 'BY',
        'BY_SA' => 'BY-SA',
        'BY_ND' => 'BY-ND',
        'BY_NC' => 'BY-NC',
        'BY_NC_SA' => 'BY-NC-SA',
        'BY_NC_ND' => 'BY-NC-ND'
    ];

    public function getPublicUrlAttribute()
    {
        return route('client.song.text', [
            'song_lyric' => $this,
            'name' => Str::slug($this->name)
        ]);
    }

    public function getPublicRouteAttribute()
    {
        return str_replace(url(""), "", $this->public_url);
    }

    public function getLyricsNoChordsAttribute()
    {
        $str = preg_replace(
            array('/-/',    '/\[[^\]]+\]/', '/@[^\s]+/',    '/^\s*#.*/m'),
            array("",       "",             "",             ""),
            $this->lyrics
        );

        return trim($str);
    }

    // TODO: implement
    public function getIsEmptyAttribute()
    {
        // return $this->lyrics == null 
        //     && $this->externals()->count() +
        //     $this->files()->count() == 0
        //     && 
    }

    public function getHasLyricsAttribute(): bool
    {
        return !empty($this->lyrics);
    }

    // public function getHasMediaAttribute() : bool
    // {
    //     return 
    //         $this->externals()->media()->exists() || 
    //         $this->files()->audio()->exists();
    // }

    // public function getHasSheetMusicAttribute() : bool
    // {
    //     return 
    //         $this->externals()->scores()->exists() ||
    //         $this->files()->scores()->exists();
    // }


    // ! deprecated soon
    public function getIsOriginalAttribute()
    {
        return $this->type == 0;
    }

    // ! deprecated soon
    public function getIsAuthorizedAttribute()
    {
        return $this->type == 2;
    }

    public function getLangStringAttribute()
    {
        return self::$lang_string_values[$this->lang];
    }

    public function getLangStringValuesAttribute()
    {
        return self::$lang_string_values;
    }

    public function getLiturgyApprovalStatusStringAttribute()
    {
        return self::$liturgy_approval_status_string_values[$this->liturgy_approval_status];
    }

    public function getLiturgyApprovalStatusStringValuesAttribute()
    {
        return self::$liturgy_approval_status_string_values;
    }

    // helper method, so this can be included in graphql schema
    public function getAuthorshipTypeStringValuesAttribute()
    {
        return AuthorSongLyric::$authorship_type_string_values;
    }

    public function getLicenceTypeCCStringValuesAttribute()
    {
        return self::$licence_type_cc_string_values;
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    // todo: make obsolete (together with an graphql endpoint)
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class);
    }

    public function authors_pivot(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)
            ->withPivot('authorship_type', 'id')
            ->using(AuthorSongLyric::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function externals(): HasMany
    {
        return $this->hasMany(External::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function arrangements(): HasMany
    {
        return $this->hasMany(SongLyric::class, 'arrangement_of', 'id');
    }

    public function arrangement_source(): BelongsTo
    {
        return $this->belongsTo(SongLyric::class, 'arrangement_of', 'id');
    }

    public function getIsArrangementAttribute(): bool
    {
        return $this->arrangement_of !== null;
    }

    public function getHasArrangementsAttribute(): bool
    {
        return $this->arrangements()->count() > 0;
    }

    public function getRichNameAttribute(): string
    {
        $name = $this->name;

        if ($this->is_arrangement) {
            $name .= " (ar. písně " . $this->arrangement_source->name;

            $author_names = $this->authors()->select('name')->get()->pluck('name');

            if ($author_names->count() > 0) {
                $name .= ", autoři: ";
                $name .= $author_names->join(', ');
            }

            $name .= ")";
        }

        return $name;
    }

    public function songbook_records(): BelongsToMany
    {
        return $this->belongsToMany(Songbook::class, "songbook_records")
            ->withPivot('number', 'placeholder', 'id')
            ->using(SongbookRecord::class);
    }

    public function scopeTranslations($query)
    {
        return $query->where('type', '!=', 0);
    }

    public function scopeOriginals($query)
    {
        return $query->where('type', 0);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', 1);
    }

    public function scopeRestricted($query)
    {
        // restrict results if current user is Author
        if (Auth::user()->hasRole('autor')) {
            return $query->forceRestricted();
        } else {
            return $query;
        }
    }

    public function scopeForceRestricted($query)
    {
        // show songs, where there is at least one common author 
        // of song authors and to-user-assigned authors
        return $query->whereHas('authors', function ($q) {
            $q->whereIn('authors.id', Auth::user()->getAssignedAuthorIds());
            // and show those songs, that were created by this user account
        })->orWhere('user_creator_id', Auth::user()->id);
    }

    public function scopeNotEmpty($query)
    {
        // return SongLyrycs that have at least one of:
        // lyrics, sheet music

        return $query->whereHas('scoreExternals')
            ->orWhereHas('scoreFiles')
            ->orWhere('lyrics', '!=', '');
    }

    /*
     * Real type collections
     */
    public function spotifyTracks()
    {
        return $this->externals()->where('media_type', 'spotify')->orderBy('is_featured', 'desc');
    }

    public function soundcloudTracks()
    {
        return $this->externals()->where('media_type', 'soundcloud')->orderBy('is_featured', 'desc');
    }

    public function youtubeVideos()
    {
        return $this->externals()->where('media_type', 'youtube')->orderBy('is_featured', 'desc');
    }

    public function audioFiles()
    {
        return $this->files()->audio();
    }

    public function scoreExternals()
    {
        return $this->externals()->scores()->orderBy('is_featured', 'desc');
    }

    public function scoreFiles()
    {
        return $this->files()->scores();
    }

    // the reason for existence of the domestic characteristic
    // is the case when there are multiple SongLyrics under one Song and no original one
    // which is permitted when the original is unknown
    // TODO: make obsolete
    public function isDomestic()
    {
        return $this->name === $this->song->name;
    }


    public function getSiblings()
    {
        return $this->song->song_lyrics()->where('id', '!=', $this->id)->get();
    }

    public function hasSiblings()
    {
        return $this->getSiblings()->count() > 0;
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $songbook_records = $this->songbook_records()->get()->map(function ($sb) {
            return [
                'songbook_id' => $sb->id,
                'songbook_number' => $sb->pivot->number,
                'songbook_number_integer' => (int)preg_replace('/\D/', '', $sb->pivot->number),
                'songbook_full_number' => [$sb->pivot->songbook->shortcut . $sb->pivot->number,  $sb->pivot->songbook->shortcut . ' ' . $sb->pivot->number],
            ];
        });

        $all_authors = $this->authors()->with('memberships')->get();
        foreach ($all_authors as $author) {
            $all_authors = $all_authors->concat($author->memberships);
        }

        // get all song's tags
        $tag_ids = $this->tags()->select('tags.id')->get()->pluck('id');

        $interestingExternals = $this->externals()
            ->with('tags')
            ->whereHas(
                'tags',
                function ($q) {
                    return $q->instrumentation();
                }
            )
            ->get();

        $interestingFiles = $this->files()
            ->with('tags')
            ->whereHas(
                'tags',
                function ($q) {
                    return $q->instrumentation();
                }
            )
            ->get();

        // adjoin externals' instrumentation tags
        foreach ($interestingExternals as $external) {
            $tag_ids = $tag_ids->concat(
                $external->tags()->instrumentation()->select('id')->get()->pluck('id')
            );
        }

        // adjoin files' instrumentation tags
        foreach ($interestingFiles as $file) {
            $tag_ids = $tag_ids->concat(
                $file->tags()->instrumentation()->select('id')->get()->pluck('id')
            );
        }

        $fullname = $this->name;
        if ($this->secondary_name_1) $fullname .= ' ' . $this->secondary_name_1;
        if ($this->secondary_name_2) $fullname .= ' ' . $this->secondary_name_2;

        $arr = [
            'name' => $fullname,
            // name_keyword is used for sorting
            'name_keyword' => $this->name,
            'song_number' => $this->song_number,
            'song_number_integer' => (int)$this->song_number,
            'lyrics' => $this->lyrics_no_chords,
            'authors' => $all_authors->pluck('name'),
            'songbook_records' => $songbook_records,
            'lang' => $this->lang,
            'is_arrangement' => $this->is_arrangement,
            'only_regenschori' => (bool)$this->only_regenschori,
            'tag_ids' => $tag_ids,
            'has_media_files_externals' => $this->externals()->media()->count() + $this->files()->audio()->count() > 0,
            'has_score_files_externals' => $this->scoreExternals()->count() + $this->scoreFiles()->count() > 0,
            'has_lyrics' => $this->has_lyrics, // a computed attribute
            'has_chords' => (bool)$this->has_chords, // an actual field precomputed in SongLyricSaved event
        ];

        return $arr;
    }

    // todo: make obsolete
    public function getFormattedLyrics()
    {
        $output = "";

        // type :: [App/Helpers/SongPart]
        $parts = SongLyricHelper::getLyricsRepresentation($this);

        $firstRefrain = current(array_filter($parts, function ($part) {
            return $part->isRefrain();
        }));

        foreach ($parts as $song_part) {
            if ($song_part->isRefrain() && $song_part->isEmpty()) {
                // substitute by the first refrain
                $subst = clone $firstRefrain;

                if ($song_part->isHidden()) {
                    $subst->setHidden(true);
                } else {
                    $subst->setHiddenText(true);
                }
                $output .= $subst->toHTML();
            } else {
                $output .= $song_part->toHTML();
            }
        }

        return $output;
    }

    public function getSongParts()
    {
        $parts = SongLyricHelper::getLyricsRepresentation($this);

        $firstRefrain = current(array_filter($parts, function ($part) {
            return $part->isRefrain();
        }));

        $newParts = [];

        foreach ($parts as $song_part) {
            if ($song_part->isRefrain() && $song_part->isEmpty()) {
                // substitute by the first refrain
                $subst = clone $firstRefrain;

                if ($song_part->isHidden()) {
                    $subst->setHidden(true);
                } else {
                    $subst->setHiddenText(true);
                }
                $newParts[] = $subst;
            } else {
                $newParts[] = $song_part;
            }
        }

        return $newParts;
    }

    // todo: make obsolete
    public static function getByIdOrCreateWithName($identificator, $uniqueName = false)
    {
        if (is_numeric($identificator)) {
            return SongLyric::find($identificator);
        } else {
            $double = SongLyric::where('name', $identificator)->first();
            if ($uniqueName && $double != null) {
                return $double;
            }

            $song       = Song::create(['name' => $identificator]);
            $song_lyric = SongLyric::create([
                'name' => $identificator,
                'song_id' => $song->id
            ]);

            return $song_lyric;
        }
    }
}
