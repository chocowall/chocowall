<?php namespace App\Repositories;

use App\Choco\NuGet\Package;
use App\Models\Group;
use Illuminate\Support\Arr;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Auth;
use LdapRecord\Query\ObjectNotFoundException;

class NugetQueryBuilder {
    /**
     * NugetRepository constructor.
     *
     */

    /**
     * @var array
     */
    protected array $groups = [];

    /**
     * @var array
     */
    protected array $packages = [];


    public function __construct()
    {
    }

    /**
     * @return mixed
     * @throws ObjectNotFoundException
     */

    public function all()
    {
        $username = Auth::user()->name;
        $user = LdapUser::findByOrFail('samaccountname', $username);
        $groups = $user->groups()->get('cn');

       $groups->each(function($group) {
           $this->groups[] = $group->cn[0];
       });

        $packages = Group::with('packages')->wherein('name', $this->groups)->get();
        $packages->each(function($package){
            $package->packages->each(function($package){
                $this->packages[] = $package->id;
            });

        });

        return Package::where('is_listed', true)->wherein('id', $this->packages);

    }

    /**
     * @var array
     */
    public $fieldMappings = [
        'Id'                       => ['field' => 'package_id'],
        'Version'                  => ['field' => 'version'],
        'Title'                    => ['field' => 'title'],
        'Dependencies'             => ['field' => 'dependencies'],
        'LicenseUrl'               => ['field' => 'license_url'],
        'Copyright'                => ['field' => 'copyright'],
        'DownloadCount'            => ['field' => 'download_count', 'type' => 'Edm.Int32'],
        'ProjectUrl'               => ['field' => 'project_url'],
        'RequireLicenseAcceptance' => ['field' => 'require_license_acceptance', 'type' => 'Edm.Boolean'],
        'GalleryDetailsUrl'        => ['function' => 'getGalleryUrl'],
        'Description'              => ['field' => 'description'],
        'ReleaseNotes'             => ['field' => 'release_notes'],
        'PackageHash'              => ['field' => 'hash'],
        'PackageHashAlgorithm'     => ['field' => 'hash_algorithm'],
        'PackageSize'              => ['field' => 'size', 'type' => 'Edm.Int64'],
        'Published'                => ['field' => 'created_at', 'type' => 'Edm.DateTime'],
        'Tags'                     => ['field' => 'tags'], //@todo
        'IsLatestVersion'          => ['field' => 'is_latest_version', 'type' => 'Edm.Boolean', 'isFilterable' => true],
        'IsPrerelease'             => ['field' => 'is_prerelease', 'type' => 'Edm.Boolean', 'isFilterable' => true],
        'VersionDownloadCount'     => ['field' => 'version_download_count', 'type' => 'Edm.Int32'],
        'Summary'                  => ['field' => 'summary'],
        'IsAbsoluteLatestVersion'  => ['field'        => 'is_absolute_latest_version', 'type' => 'Edm.Boolean',
                                       'isFilterable' => true], //@todo
        'Listed'                   => ['field' => 'is_listed', 'type' => 'Edm.Boolean'],
        'IconUrl'                  => ['field' => 'icon_url'],
        'Language'                 => ['field' => 'language'],
        //@todo ReportAbuseUrl, MinClientVersion, LastEdited, LicenseNames, LicenseReportUrl
    ];

    public function getAllProperties()
    {
        return array_keys($this->fieldMappings);
    }

    public function getMapping($property)
    {
        return $this->isProperty($property) ? $this->fieldMappings[$property] : null;
    }

    public function isProperty($property)
    {
        return Arr::has($this->fieldMappings, $property);
    }

    private function applyFilter($builder, $filter)
    {
        if (!Arr::has($this->fieldMappings, $filter))
        {
            return $builder;
        }
        $mapping = $this->fieldMappings[$filter];
        if (Arr::has($mapping, 'isFilterable') && $mapping['isFilterable'] === true)
        {
            return $builder->where($mapping['field'], true);
        }

        return $builder;
    }

    private function applyOrder($eloquent, $order)
    {
        $parts = explode(' ', $order, 2);
        $field = $parts[0];
        $order = count($parts) < 2 ? 'asc' : $parts[1];

        if (strpos($field, 'concat(') === 0)
        {
            $fields = substr($field, strlen('concat('), -1);
            foreach (explode(',', $fields) as $f)
            {
                $this->applyOrder($eloquent, $f . ' ' . $order);
            }

            return $eloquent;
        }
        if (!Arr::has($this->fieldMappings, $field))
        {
            return $eloquent;
        }
        $mapping = $this->fieldMappings[$field];

        return $eloquent->orderBy($mapping['field'], $order);
    }

    public function castType($field, $value)
    {
        $mapping = $this->fieldMappings[$field];

        if ($value === null || !Arr::has($mapping, 'type'))
        {
            return (string)$value;
        }

        switch ($mapping['type'])
        {
            case 'Edm.DateTime':
                return $value->format('Y-m-d\TH:i:s.000\Z');
            case 'Edm.Boolean':
                return $value == true ? 'true' : 'false';
            default:
                return $value;
        }
    }

    private function splitEx($input)
    {
        $result = [];
        $len = strlen($input);
        for ($i = 0; $i < $len;)
        {
            $j = $i;
            do
            {
                $s = strpos($input, ',', $j);
                $o = strpos($input, '(', $j);

                if ($s === false)
                {
                    $s = $len;
                }
                else
                {
                    if ($o !== false && $o < $s)
                    {
                        $c = strpos($input, ')', $o);
                        if ($c === false)
                        {
                            $o = false;
                        }
                        else
                        {
                            $j = $c + 1;
                        }
                    }
                }
            } while ($o !== false && $o < $s);

            $new = substr($input, $i, $s - $i);
            if (!empty($new))
            {
                array_push($result, $new);
            }
            $i = $s + 1;
        }

        return $result;
    }

    public function query($filter, $orderBy, $id = null)
    {
        $eloquent = $this->all();
        if(!empty($id))
        {
            $eloquent = $eloquent->where('package_id', $id);
        }
        if (!empty($filter))
        {
            foreach ($this->splitEx($filter) as $filterElement)
            {
                $eloquent = $this->applyFilter($eloquent, $filterElement);
            }
        }
        if (!empty($orderBy))
        {
            // Log::notice("Order by $orderBy");
            foreach ($this->splitEx($orderBy) as $order)
            {
                // Log::notice("..$order");
                $eloquent = $this->applyOrder($eloquent, $order);
            }
        }

        return $eloquent;
    }

    public function limit($eloquent, $top, $skip)
    {
        if (!empty($skip))
        {
            $eloquent = $eloquent->skip($skip);
        }
        if (!empty($top))
        {
            $top = min($top, 30);
            $eloquent = $eloquent->take($top);
        }
        else
        {
            $eloquent = $eloquent->take(30);
        }

        return $eloquent;
    }
}
