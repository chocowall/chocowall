<?php namespace App\Nuget;

use App\Models\User;
use Madnest\Madzipper\Madzipper;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use App\Choco\NuGet\Package;

class NupkgFile
{
    /**
     * @var
     */
    private $filename;
    /**
     * @var false
     */
    private $isFileInStorage;

    /**
     * @param $filename
     * @param false $isFileInStorage
     */
    public function __construct($filename, $isFileInStorage = false)
    {
        $this->filename = $filename;
        $this->isFileInStorage = $isFileInStorage;
    }

    /**
     * @return false|string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function getContents(): bool|string
    {
        return $this->isFileInStorage ? Storage::get($this->filename) : file_get_contents($this->filename);
    }

    /**
     * @return false|int
     */
    public function getSize(): bool|int
    {
        return $this->isFileInStorage ? Storage::size($this->filename) : filesize($this->filename);
    }

    /**
     * @param $filename
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function store($filename)
    {
        Storage::put($filename, $this->getContents());
        $this->filename = $filename;
    }

    /**
     * @param $algorithm
     * @return string
     */
    public function getHash($algorithm): string
    {
        return base64_encode(hash(strtolower($algorithm), file_get_contents($this->filename), true));
    }

    /**
     * Creates an instance of Nuspec from this NupkgFile instance.
     *
     * @return null|NuspecFile The function returns the created instance of Nuspec.
     */
    public function getNuspec(): ?NuspecFile
    {
        return NuspecFile::fromNupkgFile($this);
    }

    /**
     * Gets the contents of the first *.nuspec file inside this .nupkg file.
     *
     * @return bool|string  The function returns the read data or false on failure.
     */
    public function getNuspecFileContent(): bool|string
    {
        // List files in .nupkg file
        $zipper = new Madzipper;
        $fileList = $zipper->zip($this->filename)
            ->listFiles();

        // List files in .nupkg with .nuspec extension
        $nuspecFiles = array_filter($fileList, function ($item) {
            return substr($item, -7) === '.nuspec';
        });
        // If no .nuspec files exist, return false
        if (count($nuspecFiles) == 0) {
            $zipper->close();

            return false;
        }

        // Return contents of zip file
        $contents = $zipper->getFileContent(array_shift($nuspecFiles));
        return $contents;
    }

    /**
     * @param null $uploader
     * @return Package|false
     */
    public function savePackage($uploader = null): Package|bool
    {
        if ($uploader === null) {
            $uploader = User::where('email', 'system-cache@repo.local')->first();
        }

        // read specs
        $nuspec = $this->getNuspec();

        if ($nuspec === null) {
            return false;
        }

        $hash_algorithm = Config::get('choco.hash_algorithm');
        $hash_algorithm = strtoupper($hash_algorithm);

        // save or update
        $package = Package::where('package_id', $nuspec->id)
            ->where('version', $nuspec->version)
            ->first();

        if ($package === null) {
            $package = new Package();
        }
        // Apply specs to package revision
        $nuspec->apply($package);

        $package->is_absolute_latest_version = true;
        $package->is_listed = true;
      //  $package->is_prerelease = str_contains(strtolower($nuspec->version), ['alpha', 'beta', 'rc', 'prerelease']);
        $package->is_latest_version = !$package->is_prerelease;

        // Hash
        $package->hash = $this->getHash($hash_algorithm);
        $package->hash_algorithm = $hash_algorithm;
        $package->size = $this->getSize();

        // Move file
        $targetPath = $nuspec->getPackageTargetPath();
        $contents = file_get_contents($this->filename);
        Storage::put($targetPath, $contents);

        $this->filename = $targetPath;

        // notify older versions
        $absolute_latest_package = Package::where('package_id', $nuspec->id)
            ->where('is_absolute_latest_version', true)
            ->where('version', '!=', $package->version)
            ->first();

        if ($absolute_latest_package != null) {
            $absolute_latest_package->is_absolute_latest_version = false;
            $absolute_latest_package->save();

            $package->download_count = $absolute_latest_package->download_count;
        } else {
            $package->is_latest_version = true;
        }

        if (!$package->is_prerelease) {
            $latest_package = Package::where('package_id', $nuspec->id)
                ->where('is_latest_version', true)
                ->where('version', '!=', $package->version)
                ->first();

            if ($latest_package != null) {
                $latest_package->is_latest_version = false;
                $latest_package->save();
            }
        }

        $package->save();
        return $package;
    }
}
