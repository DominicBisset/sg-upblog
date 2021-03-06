# Azure PowerShell Quick Start

Finding it hard to remember this stuff, so this is a brain extension for me; hope it helps you too :) I am going to include a load of tricks, so this article serves two purposes, becoming a bit of a "Learn PowerShell by doing Azure" guide.

I will be using PowerShell ISE (just type 'ISE' into the start menu) - if you're not up and running with PowerShell already, try [my first powershell article][firstps]. You will need the 'Microsoft Azure PowerShell' tools from Web Platform Installer (type 'web plat' into the start menu) - it's normally in the Spotlight section, if not, search under 'Products'. For more detailed setup instructions, check out the MS guide to [Getting started with Azure PowerShell][getstarted].

[firstps]: ./my-first-powershell/
[getstarted]: https://docs.microsoft.com/en-us/powershell/azure/get-started-azureps/

## Log in

The login command is `Login-AzureRmAccount`. This will switch accounts if you're already logged in. Run it with no parameters.

To redirect the output from this operation into an object, use `$login = Login-AzureRmAccount`. This is handy because it stores your subscription ID which you might need later.

To see the login result, you can go to the console (`Ctrl+D`) and type `$login` to view the variable's content.

## List and Filter Subscriptions

Use `Get-AzureRmSubscription` to get a list of subscriptions. You can find the one you want using a `Where` filter:

	$subs = Get-AzureRmSubscription
	$subs | Where Name -like '*MPN*'

**N.b.** The returned fields from this command vary in name in different versions of Azure PowerShell tools. If you just installed them, you will be up to date with a version >4.x. But if your version is around 2.x, the `Name` field will be called `SubscriptionName`.

This would find a subscription where the name contains 'MPN'. **Beware** that the `-Contains` operator in PowerShell is for Array contents, not String contents, so we want `-Like`.

`Where` is an alias of `Where-Object`, and the comparison syntax above is a shorter version of `{$_.Name -like 'x'}`. The [shorter `Where` syntax was introduced in PowerShell 3.0][whereobject]. 

[whereobject]: https://ss64.com/ps/where-object.html

## Switch Subscriptions

`Get-AzureRmContext` shows your current Subscription (in the same object shape we got in our `$login` variable).

To switch to another sub:

	Select-AzureRmSubscription -SubscriptionName "Visual Studio Enterprise – MPN"

To get really clever:

	$subs | Where Name -like '*MPN*' | Select @{n='SubscriptionName';e={$_.Name}} | Select-AzureRmSubscription

To break down the snippet above:

	* `$subs` - our list of subscription objects (could be replaced with the call to `Get-AzureRmSubscription` itself)
	* `| Where Name -like '*MPN*'` as in previous section - filter the objects to just the subscription of interest (the one with 'MPN' in its name)
	* `| Select @{n='SubscriptionName';e={$_.Name}}` transform this into a new object with a SubscriptionName property equal to the Name of the subscription. The *reason* for this bit is that `Select-AzureRmSubscription` will accept a piped parameter but it **must** be called `SubscriptionName`. If you're using an old Azure Modules version around 2.x, the field will already be called `SubscriptionName` so you wouldn't need this part.
	* `| Select-AzureRmSubscription` Pipe the SubscriptionName to the cmdlet which switches for us.

I learnt that trick on this Stack answer; [how to pipe objects to a specific parameter][pipespecific]. `Select` is an alias of `Select-Object`, and when creating a hash table which will be converted into an object (`@{n='SubscriptionName';e={$_.Name}}`), both `l` and `n` are apparently acceptable aliases for `Name`, but I can't find docs for this anywhere. `e` is short for `Expression`.

[pipespecific]: https://stackoverflow.com/questions/40864264/how-to-pipe-objects-to-a-specific-parameter

## Create a Resource Group

Ok, now we're getting into workload-specific stuff, so I'm going to gather together some useful commands which you might want in conjunction with one another, for setting up some basic services.

Let's make use of some variables to prevent extra typing and reduce errors later.

	$eur = 'North Europe'
	$proj = 'paprika'
	New-AzureRmResourceGroup -Name $proj -Location $eur

You may want to deploy a resource template. I wrote an unfinished but useful [crash course to Resource Templates][restemp]. In-depth docs to [deploying Resource Manager templates][deploytemplates] are also available. Templates are very cool and I recommend looking into them.

List your resource groups with `Get-AzureRmResourceGroup`. This cmdlet has built-in filter parameters to get objects by Name, Location, etc: `Get-AzureRmResourceGroup -Name $proj`. It requires an exact name match, and you cannot use wildcards.

[restemp]: http://www.stegriff.co.uk/upblog/azure-resource-templates-crash-course
[deploytemplates]: https://docs.microsoft.com/en-us/azure/azure-resource-manager/resource-group-template-deploy

## Create a Resource

### Prologue: Names
I like my names in Azure to look like this:

	paprika          Resource Group
	paprika-plan     App Service Plan
	paprika-web      Web App
	paprikastorage   Storage (hyphens not allowed for storage accounts)
	paprika-sql      SQL Server
	paprika-db       SQL DB (on paprika-sql)

So the `$proj` variable I made earlier is going to come in handy, because I can generate a name using PowerShell's string features, i.e.

	PS > "$proj-web"
	paprika-web

### Creating resources 

You can create resources with `New-AzureRmResource` and a bunch of parameters.

(At this point I stopped writing this post to write [Discovering Azure Resource Types][discovering] because I couldn't find a good doc of common resource types - please read that post!)

You absolutely must specify a ResourceType and a Name. It seems that ResourceGroupName is also required. You probably want to specify a region and maybe some other gubbins.

[discovering]: ./discovering-azure-resource-types/

### Storage Account

To create storage (blob, table, queue...), we need a storage account, and a storage container. On a new subscription, before you can create storage, you need to register (allow) the namespace:

	Register-AzureRmResourceProvider -ProviderNamespace 'Microsoft.Storage'

The storage account is a resource and can be created with `New-AzureRmResource` or `New-AzureRmStorageAccount`. Thanks to some quirks in the implementation of the former and differences in API versions, using the latter is a lot easier. This is the first snippet where I've used backticks; these are used for line continuation.

	$proj = 'paprika'
	$eur = 'North Europe'
	$properties = @{"AccountType"='Standard_LRS'}
	
	New-AzureRmResource `
		-ResourceGroupName $proj `
		-Name ($proj + "stor") `
		-ResourceType 'Microsoft.Storage/storageAccounts' `
		-Location $eur `
		-Properties $properties `
		-ApiVersion "2015-06-15" `
		
	# OR
	
	$storage = New-AzureRmStorageAccount `
		-Location $eur `
		-Name ($proj + "stor") `
		-ResourceGroupName $proj `
		-SkuName Standard_LRS

**N.b.** If you set SkuName (AccountType) to ZRS, [you will not have access to Queues and Tables][zrs], only Blobs.

[zrs]: ./new-azurestoragequeue-no-queue-endpoint-configured/

Now that you have a storage account, you should create a container within it. To start working in relation to the storage account we have just created, we use the `Set-AzureRmCurrentStorageAccount` command (this is because there is no `AzureRm`-flavour command to create a storage container, only a classic `Azure`-flavour one, and old-school commands have no concept of ResourceGroup):

	Set-AzureRmCurrentStorageAccount -ResourceGroupName $proj -Name ($proj + "stor")
	Get-AzureRmContext

Running `Get-AzureRmContext` will show that our `CurrentStorageAccount` has changed.

Now we can create the container using `New-AzureStorageContainer`:

	New-AzureStorageContainer -Name 'files'

Let's create a storage queue. If you captured a `$storage` object during account creation, great! Otherwise you can retrieve it with the first line below:

	# Get our storage context and view it
	$storage = Get-AzureRmStorageAccount -ResourceGroupName $proj -Name ($proj + "stor")
	$storage.Context
	
	# Make a queue (won't work on ZRS)
	New-AzureStorageQueue -Context $storage.Context -Name 'test-queue' 

Okay, that should be it! You can use similar commands to create table or blob storage. Let's take a quick tour of one more well-used feature; web apps.

### Web App

Before you create your first web-based components, you will need to authorise the namespace for them, like we did for storage!

	Register-AzureRmResourceProvider -ProviderNamespace 'Microsoft.Web'
	
This has now authorised us for the relevant components, `Microsoft.Web/serverFarms` and `Microsoft.Web/sites`.

First we have to create an App Service Plan (Azure calls these 'serverFarms' internally). As before, you have a choice of cmdlets; the bog-standard `New-AzureRmResource` and the more specific `New-AzureRmAppServicePlan`. Here's the most basic call; this will default to an S1 service plan.

	New-AzureRmResource `
		-ResourceGroupName $proj `
		-Name "$proj-plantest-1" `
		-ResourceType 'Microsoft.Web/serverFarms' `
		-Location $eur
		
Specifying the Sku (the pricing tier) is pretty unwieldy, but you can do it with a hashtable like this:

	$sku = @{name='B1'; tier='Basic'; size='B1'; family='B'; capacity=1}
	New-AzureRmResource `
		-ResourceGroupName $proj `
		-Name "$proj-plantest-2" `
		-ResourceType 'Microsoft.Web/serverFarms' `
		-Location $eur `
		-Sku $sku

Here's the syntax for the more merciful cmdlet of the two:
	
	New-AzureRmAppServicePlan `
		-Location $eur `
		-Tier Basic `
		-NumberofWorkers 1 `
		-WorkerSize Small `
		-ResourceGroupName $proj `
		-Name "$proj-plantest-3"

Now that we have a service plan, we can create a web app, hooray!
