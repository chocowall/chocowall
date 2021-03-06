<?php

namespace App\Http\Controllers;

use App\Choco\Atom\AtomElement;
use App\Choco\NuGet\Package;
use App\Http\Requests\NugetRequest;
use App\Models\Group;
use App\Models\GroupPackage;
use App\Models\PackageGroup;
use Illuminate\Http\JsonResponse;
use App\Nuget\NupkgFile;
use App\Repositories\NugetQueryBuilder;
use DOMDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Artisan;
use Auth;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use LdapRecord\Query\ObjectNotFoundException;

/**
 * @OA\Info(
 *     title="Chocowall API",
 *     version="1.0.0"
 *
 * )

 * @OA\SecurityScheme(
 *     securityScheme="api_key",
 *     type="apiKey",
 *     in="header",
 *     name="x-nuget-apikey"
 * )

 * @OA\SecurityScheme(
 *     securityScheme="basicAuth",
 *     type="http",
 *     name="basic",
 *     scheme="basic"
 *
 * )
 */


class ApiController extends Controller
{
    /**
     * @var NugetQueryBuilder
     */
    private $queryBuilder;

    /**
     * @var array
     */
    protected array $packageslist = [];

    /**
     * ApiController constructor.
     *
     * @param NugetQueryBuilder $queryBuilder
     */
    public function __construct(NugetQueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Display the workspace contents.
     *
     * @return mixed
     */

    public function index(): mixed
    {
        $document = new DOMDocument('1.0', 'utf-8');
        $document->formatOutput = true;
        $service = $document->appendChild($document->createElement('service'));
        $workspace = $service->appendChild($document->createElement('workspace'));
        $workspace->appendChild($document->createElement('atom:title', 'Default'));
        $workspace->appendChild($document->createElement('collection'))
            ->appendChild($document->createElement('atom:title', 'Packages'));
        $service->setAttribute('xml:base', "");
        $service->setAttributeNS(AtomElement::XMLNS_NS, 'xmlns', 'http://www.w3.org/2007/app');
        $service->setAttributeNS(AtomElement::XMLNS_NS, 'xmlns:atom', 'http://www.w3.org/2005/Atom');

        return Response::atom($document, 200, ['Content-Type' => 'application/xml;charset=utf-8']);
    }

    /**
     * Upload a package.
     *
     * @param NugetRequest $request
     * @return mixed
     * @OA\Put(
     * path="/v2/upload",
     * summary="Upload Packages",
     * description="Upload Chocolatey nupkg file",
     * security = {{ "api_key":{} }},
     * tags = { "v2" },
     * @OA\Response(
     *    response=200,
     *    description="Status Upload",
     *    @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="ok")
     *        )
     *     )
     * )
     */
    public function upload(NugetRequest $request): mixed
    {
        $user = $request->getUser();
        $file = $request->getUploadedFile('package');

        if ($file === false) {
           \Log::error('package not uploaded on second check');
            return Response('package not uploaded on second check', 500);
        }

        Storage::makeDirectory('packages');
        $nupkg = new NupkgFile($file);
        $nupkg->savePackage($user);

        return Response::make('OK');
    }
    /**
     * @param NugetRequest $request
     * @param $id
     * @param $version
     * @return mixed
     * @OA\Delete(
     * path="/v2/package/{id}",
     * tags = { "v2" },
     * summary="Delete Packages",
     * description="Delete Chocolatey nupkg file",
     * security = {{ "api_key":{} }},
     * @OA\Response(
     *    response=200,
     *    description="Status Delete",
     *     )
     * )
     */
    public function delete(NugetRequest $request, $id, $version): mixed
    {
        $user = $request->getUser();
        if ($user) {

            $package = Package::where('package_id', $id)->where('version', $version)->firstOrFail();

            $is_latest_version = $package->is_latest_version;
            $is_absolute_latest_version = $package->is_absolute_latest_version;

            $package->delete();
            $nextVersion = Package::orderby('created_at', 'desc')->first();

            if ($nextVersion) {
                if (!$nextVersion->is_latest_version) $nextVersion->is_latest_version = $is_latest_version;
                if (!$nextVersion->is_absolute_latest_version) $nextVersion->is_absolute_latest_version = $is_absolute_latest_version;
                if ($nextVersion->isDirty()) $nextVersion->save();
            }
            return Response::make('No Content', 204);
        }
        return Response::make('Unauthorized', 403);
    }

    /**
     * Download a package.
     *
     * @param $id
     * @param $version
     * @return mixed
     * @OA\Get(
     * path="/v2/{id}/{version}",
     * tags = { "v2" },
     * summary="Download Packages",
     * description="Download Chocolatey nupkg file",
     * security = {{ "basicAuth":{} }},
     * @OA\Response(
     *    response=200,
     *    description="Download nupkg"
     *     )
     * )
     */
    public function download($id, $version = null): mixed
    {
        if (strtolower($version) === 'latest' || empty($version)) {
            $package = Package::where('package_id', $id)
                ->where('is_latest_version', true)
                ->orderBy('updated_at', 'desc')
                ->first();

        } else {
            $package = Package::where('package_id', $id)
                ->where('version', $version)
                ->first();
        }

        if (empty($package)) {
            if (empty($version)) $version = 'latest';

            $package = cachePackage($id, $version);

            if (!$package) return Response::make('not found', 404);
        }

        $package->version_download_count++;
        $package->save();

        foreach (Package::where('package_id', $id)->get() as $vPackage) {
            $vPackage->download_count++;
            $vPackage->save();
        }

        return Response::download($package->getNupkgPath());
    }

    /**
     * Search and return a specific action.
     *
     * @param $action
     * @return mixed
     * @OA\Get(
     * path="/v2/Search()/{action}",
     * tags = { "v2" },
     * summary="Search Packages with action",
     * security = {{ "basicAuth":{} }},
     * description="Search Chocolatey nupkg file with action",
     * @OA\Response(
     *    response=200,
     *    description="Download nupkg"
     *     )
     * )
     */

    public function search($action): mixed
    {
        if ($action == 'count' || $action == '$count') {
            $count = $this->processSearchQuery()
                ->count();

            return $count;
        }
    }

    /**
     * Display search results.
     *
     * @return mixed
     * @OA\Get(
     * path="/v2/Search()",
     * tags = { "v2" },
     * summary="Search Packages",
     * description="Search for packages that can be downloaded.",
     * security = {{ "basicAuth":{} }},
     * @OA\Response(
     *    response=200,
     *    description="List Packages"
     *     )
     * )
     */
    public function searchNoAction(): mixed
    {
        $eloquent = $this->processSearchQuery();
        $packages = $this->queryBuilder->limit($eloquent, Request()->get('$top'), Request()->get('$skip'))->get();

        $count = Request()->has('$inlinecount') && Request()->get('$inlinecount') == 'allpages' ? $eloquent->count()
            : count($packages);

        return $this->displayPackages($packages, route('api.search'), 'Search', time(), $count);
    }

    /**
     * Display the metadata of the API.
     *
     * @return mixed
     * @OA\Get(
     * path="/v2/$metadata",
     * tags = { "v2" },
     * summary="metadata",
     * description="metadata",
     * security = {{ "basicAuth":{} }},
     * @OA\Response(
     *    response=200,
     *    description="Display the metadata of the API.",
     *     )
     * )
     */
    public function metadata(): mixed
    {
        return Response::view('api.metadata')
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Display information about a package.
     *
     * @param $id
     * @param $version
     * @return mixed
     */
    public function package($id, $version): mixed
    {
        /** @var Package $package */
        $package = Package::where('package_id', $id)
            ->where('version', $version)
            ->first();

        if (empty($package)) {
            if (!empty($version) && !empty($version)) {
                $package = cachePackage($id, $version);
            }
            if (!$package) return $this->generateResourceNotFoundError('Packages');
        }

        $atomElement = $package->getAtomElement();
        $this->addPackagePropertiesToAtomElement($package, $this->queryBuilder->getAllProperties(), $atomElement);
        return Response::atom($atomElement->getDocument(route('api.index')));
    }

    /**
     * @var array
     */
    protected array $packages = [];

    /**
     * Display all packages.
     *
     * @param Request $request
     * @return mixed
     * @throws ObjectNotFoundException
     * @OA\Get(
     * path="/v2/Packages()",
     * tags = { "v2" },
     * security = {{ "basicAuth":{} }},
     * summary="List Packages",
     * description="Lists all packages that are authorized to download.",
     * @OA\Response(
     *    response=200,
     *    description="Lists all packages that are authorized to download.",
     *     )
     * )
     */
    public function packages(Request $request): mixed
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
                $this->packageslist[] = $package->id;
            });
        });

        $filter = Request()->get('$filter');
        $orderby = Request()->get('$orderby');
        $id = trim(Request()->get('id'), "' \t\n\r\0\x0B");

        // Handle latest version request
        if (Str::contains($filter, 'IsLatestVersion')) {
            if (Str::contains($filter, 'tolower(Id) eq \'')) {
                // Get the package ID
                $id =  Str::between($filter, 'tolower(Id) eq \'', '\')');
                $key = 'latest-' . $id;
                // Get the latest version from the cache
                $version = Cache::remember($key, 21600, function () use ($id) {
                    // If not in the cache determine the latest version and cache it
                    $package = cachePackage($id, 'latest');
                    return $package->version;
                });
                $package = Package::where('package_id', $id)
                    ->where('version', $version)->wherein('id', $this->packageslist)
                    ->first();

                return $this->displayPackages([$package], route('api.packages'), 'Packages', time(), 1);
            }
        }

        $eloquent = $this->queryBuilder->query($filter, $orderby, $id);
        $packages = $this->queryBuilder->limit($eloquent, Request()->get('$top'), Request()->get('$skip'))
            ->get();

        $count = Request()->has('$inlinecount') && Request()->get('$inlinecount') == 'allpages' ? $eloquent->count()
            : count($packages);

        return $this->displayPackages($packages, route('api.packages'), 'Packages', time(), $count);
    }

    /**
     * Display all available updates.
     *
     * @return mixed
     * @OA\Get(
     * path="/v2/GetUpdates()",
     * tags = { "v2" },
     * summary="Update Packages",
     * security = {{ "api_key":{} }},
     * description="Update Packages Information",
     * @OA\Response(
     *    response=200,
     *    description="Display all available updates",
     *     )
     * )
     */
    public function updates(): mixed
    {
        // Read Request.
        $package_ids = explode('|', trim(Request()->get('packageIds'), "'"));
        $package_versions = explode('|', trim(Request()->get('versions'), "'"));
        $include_prerelease = Request()->get('includePrerelease') === 'true';

        if (count($package_ids) != count($package_versions)) {
            return $this->generateError('Invalid version count', 'eu-US', 301);
        }

        // Query database.
        $packages = [];
        foreach ($package_ids as $index => $id) {
            $version = $package_versions[$index];
            $builder = Package::where('package_id', $id);
            if (!$include_prerelease) {
                $builder = $builder->where('is_prerelease', false);
            }
            $latest = $builder->orderBy('created_at', 'desc')
                ->first();
            if ($latest != null && $latest->version != $version) {
                array_push($packages, $latest);
            }
        }

        return $this->displayPackages($packages, route('api.updates'), 'GetUpdates', time(), count($packages));
    }

    /**
     * @param $message
     * @param string $language
     * @param string $status
     * @return mixed
     */
    private function generateError($message, $language = 'en-US', $status = '404'): mixed
    {
        $document = new DOMDocument('1.0', 'utf-8');
        $document->formatOutput = true;
        $error = $document->appendChild($document->createElement('m:error'));
        $error->appendChild($document->createElement('m:code'));
        $error->appendChild($document->createElement('m:message', $message))
            ->setAttribute('xml:lang', $language);
        $error->setAttributeNS(AtomElement::XMLNS_NS, 'xmlns:m', 'http://schemas.microsoft.com/ado/2007/08/dataservices/metadata');

        return Response::atom($document, $status, ['Content-Type' => 'application/xml;charset=utf-8']);
    }

    private function generateResourceNotFoundError($segmentName)
    {
        return $this->generateError("Resource not found for the segment '$segmentName'.", 'en-US', 404);
    }

    /**
     * @param Package $package
     * @param array $properties
     * @param AtomElement $atomElement
     */
    private function addPackagePropertiesToAtomElement($package, $properties, $atomElement)
    {
        foreach ($properties as $property) {
            if (!$this->queryBuilder->isProperty($property)) {
                continue;
            }

            $mapping = $this->queryBuilder->getMapping($property);

            if (Arr::has($mapping, 'function')) {
                $func = $mapping['function'];
                $value = $package->$func();
            } else {
                if (Arr::has($mapping, 'field')) {
                    $field = $mapping['field'];
                    $value = $package->$field;
                }
            }

            $atomElement->addProperty($property, $this->queryBuilder->castType($property, $value), Arr::has($mapping, 'type')
                ? $mapping['type'] : null);
        }
    }

    /**
     * Display a list of packages.
     *
     * @param        $packages
     * @param        $id
     * @param        $title
     * @param        $updated
     * @param mixed $count
     * @return mixed
     */
    private function displayPackages($packages, $id, $title, $updated, $count = false): mixed
    {
        $properties = Request()->has('$select')
            ? array_filter(explode(',', Request()->get('$select')), function ($name) {
                return $this->queryBuilder->isProperty($name);
            })
            : $this->queryBuilder->getAllProperties();

        $atom = with(new AtomElement('feed', $id, $title, $updated))
            ->addLink('self', $title, $title)
            ->setCount($count);

        /** @var Package $package */
        foreach ($packages as $package) {
            $atomElement = $package->getAtomElement();
            $this->addPackagePropertiesToAtomElement($package, $properties, $atomElement);
            $atom->appendChild($atomElement);
        }

        return Response::atom($atom->getDocument(route('api.index')));
    }

    /**
     * Build a query based on the Request.
     *
     * @return mixed
     */
    private function processSearchQuery(): mixed
    {
        // Read Request.
        //@todo: Improve search_term querying (split words?)
        $search_term = trim(Request()->get('searchTerm', ''), '\' \t\n\r\0\x0B');
        $target_framework = Request()->get('targetFramework');//@todo ;; eg. "'net45'"
        $include_prerelease = Request()->get('includePrerelease') === 'true';

        // Query database.
        $eloquent = $this->queryBuilder->query(Request()->get('$filter'), Request()->get('$orderby'));

        if (!empty($search_term)) {
            $eloquent = $eloquent->where(function ($query) use ($search_term) {
                $query->where('package_id', 'LIKE', "%$search_term%");
                $query->orWhere('title', 'LIKE', "%$search_term%");
                $query->orWhere('description', 'LIKE', "%$search_term%");
                $query->orWhere('summary', 'LIKE', "%$search_term%");
                $query->orWhere('tags', 'LIKE', "%$search_term%");
                $query->orWhere('authors', 'LIKE', "%$search_term%");
            });
        }
        if (!$include_prerelease) {
            $eloquent->where('is_prerelease', false);
        }

        return $eloquent;
    }

    /**
     * Download a package.
     *
     * @param Request $request
     * @param $id
     * @return JsonResponse
     * @OA\Get(
     * path="/v2/package/{id}",
     * tags = { "v2" },
     * summary="Update Package Authorization",
     * security = {{ "basicAuth":{} }},
     * description="Update Package file authorization",
     * @OA\Response(
     *    response=200,
     *    description="Update Package file authorization",
     *    @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="ok")
     *        )
     *     )
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {

        $version = $request->version;
        $is_listed = $request->is_listed;
        $groups = $request->groups;

        if (strtolower($version) === 'latest' || empty($version)) {
            $package = Package::where('package_id', $id)
                ->where('is_latest_version', true)
                ->first();
        } else {
            $package = Package::where('package_id', $id)
                ->where('version', $version)
                ->first();
        }

        if (empty($package)) {
            return response()->json(['message' => "$id not found"], 404);
        }

        if (!empty($groups))
        {
            $availableGroups = collect(group::all(['name']))->pluck('name');
            $unavailableGroups  = collect($groups)->diff($availableGroups)->implode(', ');

            if ($unavailableGroups > 0) {
                Artisan::call('ldap:import:groups');

                $availableGroups = collect(group::all(['name']))->pluck('name');
                $unavailableGroups  = collect($groups)->diff($availableGroups)->implode(', ');

                if ($unavailableGroups > 0) {
                    return response()->json(['message' => "Groups $unavailableGroups not found"], 404);
                }
            }

            foreach ($groups as $group) {
                 $group = Group::where('name', $group)->first();
                 $grouppackage = GroupPackage::firstOrNew(
                            ['group_id' => $group->id, 'package_id' => $package->id]
                 );
                 $grouppackage->save();
            }
        }

        if (isset($is_listed)) {
            Package::where('id', $package->id)->update([
                    "is_listed" => $is_listed
            ]);
        }

        return response()->json(['message' => "OK"]);
    }
}
