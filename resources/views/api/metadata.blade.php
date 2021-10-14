<edmx:Edmx xmlns:edmx="http://schemas.microsoft.com/ado/2007/06/edmx" Version="1.0">
    <edmx:DataServices xmlns:m="http://schemas.microsoft.com/ado/2007/08/dataservices/metadata" m:DataServiceVersion="2.0" m:MaxDataServiceVersion="2.0">
        <Schema xmlns="http://schemas.microsoft.com/ado/2006/04/edm" Namespace="NuGetGallery">
            <EntityType Name="V2FeedPackage" m:HasStream="true">
                <Key>
                    <PropertyRef Name="Id"/>
                    <PropertyRef Name="Version"/>
                </Key>
                <Property Name="Id" Type="Edm.String" Nullable="false" m:FC_TargetPath="SyndicationTitle" m:FC_ContentKind="text" m:FC_KeepInContent="false"/>
                <Property Name="Version" Type="Edm.String" Nullable="false"/>
                <Property Name="NormalizedVersion" Type="Edm.String"/>
                <Property Name="Authors" Type="Edm.String" m:FC_TargetPath="SyndicationAuthorName" m:FC_ContentKind="text" m:FC_KeepInContent="false"/>
                <Property Name="Copyright" Type="Edm.String"/>
                <Property Name="Created" Type="Edm.DateTime" Nullable="false"/>
                <Property Name="Dependencies" Type="Edm.String"/>
                <Property Name="Description" Type="Edm.String"/>
                <Property Name="DownloadCount" Type="Edm.Int32" Nullable="false"/>
                <Property Name="GalleryDetailsUrl" Type="Edm.String"/>
                <Property Name="IconUrl" Type="Edm.String"/>
                <Property Name="IsLatestVersion" Type="Edm.Boolean" Nullable="false"/>
                <Property Name="IsAbsoluteLatestVersion" Type="Edm.Boolean" Nullable="false"/>
                <Property Name="IsPrerelease" Type="Edm.Boolean" Nullable="false"/>
                <Property Name="Language" Type="Edm.String"/>
                <Property Name="LastUpdated" Type="Edm.DateTime" Nullable="false" m:FC_TargetPath="SyndicationUpdated" m:FC_ContentKind="text" m:FC_KeepInContent="false"/>
                <Property Name="Published" Type="Edm.DateTime" Nullable="false"/>
                <Property Name="PackageHash" Type="Edm.String"/>
                <Property Name="PackageHashAlgorithm" Type="Edm.String"/>
                <Property Name="PackageSize" Type="Edm.Int64" Nullable="false"/>
                <Property Name="ProjectUrl" Type="Edm.String"/>
                <Property Name="ReportAbuseUrl" Type="Edm.String"/>
                <Property Name="ReleaseNotes" Type="Edm.String"/>
                <Property Name="RequireLicenseAcceptance" Type="Edm.Boolean" Nullable="false"/>
                <Property Name="Summary" Type="Edm.String" m:FC_TargetPath="SyndicationSummary" m:FC_ContentKind="text" m:FC_KeepInContent="false"/>
                <Property Name="Tags" Type="Edm.String"/>
                <Property Name="Title" Type="Edm.String"/>
                <Property Name="VersionDownloadCount" Type="Edm.Int32" Nullable="false"/>
                <Property Name="MinClientVersion" Type="Edm.String"/>
                <Property Name="LastEdited" Type="Edm.DateTime"/>
                <Property Name="LicenseUrl" Type="Edm.String"/>
                <Property Name="LicenseNames" Type="Edm.String"/>
                <Property Name="LicenseReportUrl" Type="Edm.String"/>
            </EntityType>
            <EntityContainer Name="V2FeedContext" m:IsDefaultEntityContainer="true">
                <EntitySet Name="Packages" EntityType="NuGetGallery.V2FeedPackage"/>
                <FunctionImport Name="Search" ReturnType="Collection(NuGetGallery.V2FeedPackage)" EntitySet="Packages" m:HttpMethod="GET">
                    <Parameter Name="searchTerm" Type="Edm.String"/>
                    <Parameter Name="targetFramework" Type="Edm.String"/>
                    <Parameter Name="includePrerelease" Type="Edm.Boolean"/>
                </FunctionImport>
                <FunctionImport Name="FindPackagesById" ReturnType="Collection(NuGetGallery.V2FeedPackage)" EntitySet="Packages" m:HttpMethod="GET">
                    <Parameter Name="id" Type="Edm.String"/>
                </FunctionImport>
                <FunctionImport Name="GetUpdates" ReturnType="Collection(NuGetGallery.V2FeedPackage)" EntitySet="Packages" m:HttpMethod="GET">
                    <Parameter Name="packageIds" Type="Edm.String"/>
                    <Parameter Name="versions" Type="Edm.String"/>
                    <Parameter Name="includePrerelease" Type="Edm.Boolean"/>
                    <Parameter Name="includeAllVersions" Type="Edm.Boolean"/>
                    <Parameter Name="targetFrameworks" Type="Edm.String"/>
                    <Parameter Name="versionConstraints" Type="Edm.String"/>
                </FunctionImport>
            </EntityContainer>
        </Schema>
    </edmx:DataServices>
</edmx:Edmx>
